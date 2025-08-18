<?php declare(strict_types=1);

namespace Xentral\LaravelApi;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeVisitorAbstract;

class FunctionalDocBlockExtractor extends NodeVisitorAbstract
{
    public array $foundDocs = [];

    private ?string $currentNamespace = null;

    private ?string $currentClassName = null;

    private string $currentFilePath = '';

    /**
     * Set the path of the file currently being parsed.
     */
    public function setCurrentFilePath(string $path): void
    {
        $this->currentFilePath = $path;
    }

    /**
     * Called when the traverser enters a node.
     * We use this to track the current class context and find doc comments.
     */
    public function enterNode(Node $node): null
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name ? $node->name->toString() : null;
        }
        if ($node instanceof Node\Stmt\Class_ && $node->name) {
            $this->currentClassName = $node->name->toString();
        }

        // Process the doc comment if it exists
        if ($node->getDocComment()) {
            $docCommentText = $node->getDocComment()->getText();
            $ownerIdentifier = $this->getOwnerIdentifier($node);
            $defaultTitle = $this->getDefaultTitleForNode($node);

            $parsedDoc = $this->parseDocComment($docCommentText, $defaultTitle, $ownerIdentifier, $node->getStartLine());

            if ($parsedDoc) {
                $this->foundDocs[] = $parsedDoc;
            }
        }

        return null;
    }

    /**
     * Called when the traverser leaves a node.
     * We use this to reset the class context.
     */
    public function leaveNode(Node $node): null
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->currentClassName = null;
        }
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = null;
        }

        return null;
    }

    /**
     * Parses a PHPDoc comment string to extract the functional description and nav path.
     * This function contains several processing passes to clean and format the text correctly.
     *
     * @return array{owner: string, navPath: string, description: string, links: array, uses: array, sourceFile: string, startLine: int}|null
     */
    private function parseDocComment(string $docComment, string $defaultTitle, string $ownerIdentifier, int $startLine): ?array
    {
        // Quickly check if the block is functional at all.
        if (! str_contains($docComment, '@functional')) {
            return null;
        }

        $lines = explode("\n", $docComment);
        $navPath = null;
        $links = [];
        $uses = [];

        // --- Pass 1: Extract metadata (nav path, links, uses) ---
        foreach ($lines as $line) {
            $cleanLine = ltrim(trim($line), '* ');

            if (str_starts_with($cleanLine, '@nav')) {
                $navPath = trim(substr($cleanLine, strlen('@nav')));
            } elseif (str_starts_with($cleanLine, '@uses')) {
                $uses[] = trim(substr($cleanLine, strlen('@uses')));
            } elseif (str_starts_with($cleanLine, '@links')) {
                $links[] = trim(substr($cleanLine, strlen('@links')));
            } elseif (str_starts_with($cleanLine, '@link')) {
                $links[] = trim(substr($cleanLine, strlen('@link')));
            }
        }

        // If no nav path, create a default one.
        if ($navPath === null) {
            $navPath = 'Uncategorised / '.$defaultTitle;
        }

        // --- Pass 2: Strictly isolate the @functional description block ---
        $rawFunctionalLines = [];
        $inFunctionalBlock = false;

        foreach ($lines as $line) {
            $testLine = preg_replace('/^\s*\*\s?/', '', $line);
            if (str_starts_with(trim((string) $testLine), '@functional')) {
                $inFunctionalBlock = true;
                $lineContent = preg_replace('/@functional\s*/', '', (string) $testLine, 1);
                if (trim((string) $lineContent) !== '') {
                    $rawFunctionalLines[] = $lineContent;
                }

                continue;
            }

            if ($inFunctionalBlock) {
                if (str_starts_with(trim((string) $testLine), '@')) {
                    break;
                }
                $rawFunctionalLines[] = $testLine;
            }
        }

        // --- Pass 3: De-indent the isolated block ---
        $minIndent = null;
        $inCodeFence = false;
        foreach ($rawFunctionalLines as $line) {
            if (str_starts_with(trim((string) $line), '```')) {
                $inCodeFence = ! $inCodeFence;

                continue;
            }
            if (! $inCodeFence && trim((string) $line) !== '') {
                preg_match('/^(\s*)/', (string) $line, $matches);
                $currentIndent = strlen($matches[1]);
                if ($minIndent === null || $currentIndent < $minIndent) {
                    $minIndent = $currentIndent;
                }
            }
        }

        $deindentedLines = [];
        if ($minIndent > 0) {
            foreach ($rawFunctionalLines as $line) {
                if (trim((string) $line) === '') {
                    $deindentedLines[] = $line;

                    continue;
                }
                if (str_starts_with((string) $line, str_repeat(' ', $minIndent))) {
                    $deindentedLines[] = substr((string) $line, $minIndent);
                } else {
                    $deindentedLines[] = $line;
                }
            }
        } else {
            $deindentedLines = $rawFunctionalLines;
        }

        // --- Pass 4: Post-process to fix Markdown list rendering ---
        $listFixedLines = [];
        if (! empty($deindentedLines)) {
            $listFixedLines[] = $deindentedLines[0];
            for ($i = 1; $i < count($deindentedLines); $i++) {
                $currentLine = $deindentedLines[$i];
                $previousLine = $deindentedLines[$i - 1];
                $trimmedCurrent = ltrim((string) $currentLine);
                $isListItem = str_starts_with($trimmedCurrent, '- ') || str_starts_with($trimmedCurrent, '* ') || preg_match('/^\d+\.\s/', $trimmedCurrent);
                if ($isListItem && trim((string) $previousLine) !== '') {
                    $trimmedPrevious = ltrim((string) $previousLine);
                    $previousIsListItem = str_starts_with($trimmedPrevious, '- ') || str_starts_with($trimmedPrevious, '* ') || preg_match('/^\d+\.\s/', $trimmedPrevious);
                    if (! $previousIsListItem) {
                        $listFixedLines[] = '';
                    }
                }
                $listFixedLines[] = $currentLine;
            }
        }

        // --- Pass 5: Demote all user-defined headings by one level ---
        $finalLines = [];
        foreach ($listFixedLines as $line) {
            $trimmedLine = ltrim((string) $line);
            if (str_starts_with($trimmedLine, '#')) {
                $finalLines[] = '#'.$trimmedLine;
            } else {
                $finalLines[] = $line;
            }
        }

        if (empty($finalLines) && empty($uses)) {
            return null;
        }

        return [
            'owner' => $ownerIdentifier,
            'navPath' => $navPath,
            'description' => implode("\n", $finalLines),
            'links' => $links,
            'uses' => $uses,
            'sourceFile' => $this->currentFilePath,
            'startLine' => $startLine,
        ];
    }

    private function getOwnerIdentifier(Node $node): string
    {
        $namespace = $this->currentNamespace ? $this->currentNamespace.'\\' : '';
        if ($node instanceof Node\Stmt\Class_) {
            $fqcn = $namespace.$node->name->toString();

            return ltrim($fqcn, '\\');
        }
        if ($node instanceof ClassMethod && $this->currentClassName) {
            $fqcn = $namespace.$this->currentClassName.'::'.$node->name->toString();

            return ltrim($fqcn, '\\');
        }
        if ($node instanceof Function_) {
            $fqcn = $namespace.$node->name->toString();

            return ltrim($fqcn, '\\');
        }

        return 'unknown_owner_'.uniqid();
    }

    private function getDefaultTitleForNode(Node $node): string
    {
        if ($node instanceof Node\Stmt\Class_ && $node->name) {
            return $node->name->toString();
        }
        if ($node instanceof ClassMethod && $this->currentClassName) {
            return $this->currentClassName.'::'.$node->name->toString();
        }
        if ($node instanceof Function_) {
            return $node->name->toString();
        }

        return 'Untitled Document';
    }
}
