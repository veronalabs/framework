<?php // phpcs:ignore WordPress.Files.FileName
/**
 * Form render interface.
 *
 * @package   backyard-framework
 * @author    Sematico LTD <hello@sematico.com>
 * @copyright 2020 Sematico LTD
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://sematico.com
 */

namespace Backyard\Contracts;

interface FormRendererInterface {

	/**
	 * Render the form through a custom layout.
	 *
	 * @return string
	 */
	public function render();

}
