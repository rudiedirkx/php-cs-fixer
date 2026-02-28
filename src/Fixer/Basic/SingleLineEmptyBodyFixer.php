<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Fixer\Basic;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\ConfigurableFixerTrait;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\FCT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise.
 */
final class SingleLineEmptyBodyFixer extends AbstractFixer implements ConfigurableFixerInterface
{
    use ConfigurableFixerTrait;

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Empty body of class, interface, trait, enum or function must be abbreviated as `{}` and placed on the same line as the previous symbol, separated by a single space.',
            [
                new CodeSample(
                    <<<'PHP'
                        <?php function foo(
                            int $x
                        )
                        {
                        }

                        PHP,
                ),
                new CodeSample('<?php function foo(
    int $x
)
{
}
'
                ),
                new CodeSample('<?php
class Foo
{
    public function __construct()
    {
    }

    public function foo()
    {
    }
}
',
                    ['only_for_constructors' => true]
                )
            ],
        );
    }

    /**
     * {@inheritdoc}
     *
     * Must run after BracesPositionFixer, ClassDefinitionFixer, CurlyBracesPositionFixer, NoUselessReturnFixer.
     */
    public function getPriority(): int
    {
        return -19;
    }

    protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            (new FixerOptionBuilder('only_for_constructors', 'Whether to ONLY fold __construct() methods, nothing else.'))
                ->setAllowedTypes(['bool'])
                ->setDefault(false)
                ->getOption(),
        ]);
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([\T_INTERFACE, \T_CLASS, \T_FUNCTION, \T_TRAIT, FCT::T_ENUM]);
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
        for ($index = $tokens->count() - 1; $index > 0; --$index) {
            if (!$tokens[$index]->isGivenKind([...Token::getClassyTokenKinds(), \T_FUNCTION])) {
                continue;
            }

            if (true === $this->configuration['only_for_constructors']) {
                $funcNameIndex = $tokens->getNextNonWhitespace($index);
                if ('__construct' !== $tokens[$funcNameIndex]->getContent()) {
                    continue;
                }
            }

            $openBraceIndex = $tokens->getNextTokenOfKind($index, ['{', ';']);
            if (!$tokens[$openBraceIndex]->equals('{')) {
                continue;
            }

            $closeBraceIndex = $tokens->getNextNonWhitespace($openBraceIndex);
            if (!$tokens[$closeBraceIndex]->equals('}')) {
                continue;
            }

            $tokens->ensureWhitespaceAtIndex($openBraceIndex + 1, 0, '');

            $beforeOpenBraceIndex = $tokens->getPrevNonWhitespace($openBraceIndex);
            if (!$tokens[$beforeOpenBraceIndex]->isGivenKind([\T_COMMENT, \T_DOC_COMMENT])) {
                $tokens->ensureWhitespaceAtIndex($openBraceIndex - 1, 1, ' ');
            }
        }
    }
}
