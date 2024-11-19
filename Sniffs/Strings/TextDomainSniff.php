<?php

namespace ThemeisleSniffs\Sniffs\Strings;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class TextDomainSniff implements Sniff
{
	/**
	 * List of WordPress translation functions.
	 *
	 * @var array
	 */
	private $translationFunctions = array(
		'__',
		'_e',
		'_x',
		'_n',
		'_nx',
		'esc_html__',
		'esc_html_e',
		'esc_html_x',
		'esc_attr__',
		'esc_attr_e',
		'esc_attr_x',
	);

	/**
	 * The source text domain to be replaced.
	 *
	 * @var string
	 */
	public $originalTextDomain = '';

	/**
	 * The target text domain to replace with.
	 *
	 * @var string
	 */
	public $targetTextDomain = '';

	/**
	 * Register the tokens to listen for.
	 *
	 * @return array
	 */
	public function register()
	{
		return array(T_STRING);
	}

	/**
	 * Process the sniff when a matching token is found.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr  The position of the current token in the stack.
	 * @return void
	 */
	public function process($phpcsFile, $stackPtr)
	{
		if ( empty( $this->targetTextDomain ) ) {
			return;
		}

		$tokens = $phpcsFile->getTokens();

		if (!$this->isTranslationFunction($tokens[$stackPtr]['content'])) {
			return;
		}

		$functionName = $tokens[$stackPtr]['content'];

		$openParenthesis = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr);
		if ($openParenthesis === false) {
			return;
		}

		$arguments = $this->getFunctionArguments($tokens, $openParenthesis);

		// Check if we have any arguments
		if (empty($arguments)) {
			return;
		}

		// Get the last argument which should be the text domain
		$textDomainPtr = end($arguments);

		if ($textDomainPtr === false || !$this->isTextDomainMatch($tokens, $textDomainPtr)) {
			return;
		}

		$this->addFixableError($phpcsFile, $textDomainPtr, $functionName);
	}

	/**
	 * Check if the given function name is a translation function.
	 *
	 * @param string $functionName The name of the function.
	 * @return bool
	 */
	private function isTranslationFunction($functionName)
	{
		return in_array($functionName, $this->translationFunctions, true);
	}

	/**
	 * Get the arguments of a function call.
	 *
	 * @param array $tokens          The tokens of the file being scanned.
	 * @param int   $openParenthesis The position of the opening parenthesis.
	 * @return array<int, int> Array of argument positions.
	 */
	private function getFunctionArguments($tokens, $openParenthesis)
	{
		$arguments = array();
		$level = 1;
		$currentArgStart = $openParenthesis + 1;

		for ($i = $openParenthesis + 1; $i < count($tokens); $i++) {
			if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
				$level++;
			} elseif ($tokens[$i]['code'] === T_CLOSE_PARENTHESIS) {
				$level--;
				if ($level === 0) {
					// Add the last argument
					$lastArg = $this->findNextStringToken($tokens, $currentArgStart, $i);
					if ($lastArg !== null) {
						$arguments[] = $lastArg;
					}
					break;
				}
			} elseif ($tokens[$i]['code'] === T_COMMA && $level === 1) {
				$stringToken = $this->findNextStringToken($tokens, $currentArgStart, $i);
				if ($stringToken !== null) {
					$arguments[] = $stringToken;
				}
				$currentArgStart = $i + 1;
			}
		}

		return $arguments;
	}

	/**
	 * Find the next constant-encased string token within a range.
	 *
	 * @param array $tokens The tokens of the file being scanned.
	 * @param int   $start  The start position to search from.
	 * @param int   $end    The end position to search to.
	 * @return int|null Position of the string token or null if not found.
	 */
	private function findNextStringToken($tokens, $start, $end)
	{
		for ($i = $start; $i < $end; $i++) {
			if ($tokens[$i]['code'] === T_CONSTANT_ENCAPSED_STRING) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Check if the text domain matches the original text domain.
	 *
	 * @param array $tokens       The tokens of the file being scanned.
	 * @param int   $textDomainPtr The position of the text domain token.
	 * @return bool
	 */
	private function isTextDomainMatch($tokens, $textDomainPtr)
	{
		$textDomain = trim($tokens[$textDomainPtr]['content'], '\'"');
		return $textDomain === $this->originalTextDomain;
	}

	/**
	 * Add a fixable error for replacing the text domain.
	 *
	 * @param File  $phpcsFile    The file being scanned.
	 * @param int   $textDomainPtr The position of the text domain token.
	 * @param string $functionName The name of the function.
	 * @return void
	 */
	private function addFixableError($phpcsFile, $textDomainPtr, $functionName)
	{
		$error = sprintf(
			'Text domain "%s" in function %s() should be replaced with "%s".',
			$this->originalTextDomain,
			$functionName,
			$this->targetTextDomain
		);

		$fix = $phpcsFile->addFixableError($error, $textDomainPtr, 'ReplaceDomain');

		if ($fix) {
			$phpcsFile->fixer->replaceToken($textDomainPtr, "'".$this->targetTextDomain."'");
		}
	}
}
