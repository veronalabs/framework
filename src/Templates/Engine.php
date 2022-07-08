<?php // phpcs:ignore WordPress.Files.FileName
/**
 * Native PHP template system that’s fast, easy to use and easy to extend.
 * Based on plates from phpleague.
 *
 * @package   backyard-framework
 * @author    Sematico LTD <hello@sematico.com>
 * @copyright 2020 Sematico LTD
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://sematico.com
 */

namespace Backyard\Templates;

use Backyard\Application;
use Backyard\Contracts\TemplatesEngineExtensionInterface;
use Backyard\Templates\Template\Data;
use Backyard\Templates\Template\FileExtension;
use Backyard\Templates\Template\Folders;
use Backyard\Templates\Template\Func;
use Backyard\Templates\Template\Functions;
use Backyard\Templates\Template\Name;
use Backyard\Templates\Template\Template;

/**
 * Templates api and env storage.
 */
class Engine {

	/**
	 * Directory name where custom templates for this plugin should be found in the theme.
	 *
	 * For example: 'your-plugin-templates'.
	 *
	 * @var string
	 */
	protected $themeTemplatesDirectory;

	/**
	 * Directory name where templates are found in this plugin.
	 *
	 * e.g. 'templates' or 'includes/templates', etc.
	 *
	 * @var string
	 */
	protected $pluginTemplatesDirectory;

	/**
	 * Path to the plugin templates directory
	 *
	 * @var string
	 */
	protected $pluginTemplatesPath;

	/**
	 * Template file extension.
	 *
	 * @var FileExtension
	 */
	protected $fileExtension;

	/**
	 * Collection of template folders.
	 *
	 * @var Folders
	 */
	protected $folders;

	/**
	 * Collection of template functions.
	 *
	 * @var Functions
	 */
	protected $functions;

	/**
	 * Collection of preassigned template data.
	 *
	 * @var Data
	 */
	protected $data;

	/**
	 * Get things started.
	 *
	 * @param string $pluginTemplatesDirectory
	 * @param string $themeTemplatesDirectory
	 * @param string $fileExtension
	 */
	public function __construct( string $pluginTemplatesDirectory, string $themeTemplatesDirectory = null, string $fileExtension = 'php' ) {
		$this->fileExtension = new FileExtension( $fileExtension );
		$this->folders       = new Folders();
		$this->functions     = new Functions();
		$this->data          = new Data();

		$this->setPluginTemplatesPath( $pluginTemplatesDirectory );

		$this->addFolder( 'base', $this->getPluginTemplatesPath(), 100 );

		if ( ! empty( $themeTemplatesDirectory ) ) {
			$this->themeTemplatesDirectory = $themeTemplatesDirectory;

			if ( is_dir( trailingslashit( get_template_directory() ) . $this->themeTemplatesDirectory ) ) {
				$this->addFolder( 'theme', trailingslashit( get_template_directory() ) . $this->themeTemplatesDirectory, 10 );
			}

			// Only add this conditionally, so non-child themes don't redundantly check active theme twice.
			if ( get_stylesheet_directory() !== get_template_directory() ) {
				$this->addFolder( 'child-theme', trailingslashit( get_stylesheet_directory() ) . $this->themeTemplatesDirectory, 1 );
			}
		}
	}

	/**
	 * Setup the path to plugin templates directory.
	 *
	 * @param string $directoryName name of the folder.
	 * @return Engine
	 */
	public function setPluginTemplatesPath( $directoryName ) {

		$this->pluginTemplatesDirectory = $directoryName;
		$this->pluginTemplatesPath      = ( Application::get() )->plugin->basePath( $directoryName );

		return $this;

	}

	/**
	 * Get the path to the plugin templates folder.
	 *
	 * @return string
	 */
	public function getPluginTemplatesPath() {
		return $this->pluginTemplatesPath;
	}

	/**
	 * Set the template file extension.
	 *
	 * @param  string|null $fileExtension Pass null to manually set it.
	 * @return Engine
	 */
	public function setFileExtension( $fileExtension ) {
		$this->fileExtension->set( $fileExtension );

		return $this;
	}

	/**
	 * Get the template file extension.
	 *
	 * @return string
	 */
	public function getFileExtension() {
		return $this->fileExtension->get();
	}

	/**
	 * Add a new template folder to where the engine should look for files.
	 *
	 * @param string     $name
	 * @param string     $directory
	 * @param string|int $priority decide with which priority the folder should be set to.
	 * @return Engine
	 */
	public function addFolder( string $name, string $directory, $priority = 20 ) {
		$this->folders->add( $name, $directory, $priority );

		return $this;
	}

	/**
	 * Remove a template folder.
	 *
	 * @param string $name folder name
	 * @return Engine
	 */
	public function removeFolder( $name ) {
		$this->folders->remove( $name );

		return $this;
	}

	/**
	 * Get collection of all template folders.
	 *
	 * @return Folders
	 */
	public function getFolders() {
		return $this->folders;
	}

	/**
	 * Add preassigned template data.
	 *
	 * @param  array             $data
	 * @param  null|string|array $templates
	 * @return Engine
	 */
	public function addData( array $data, $templates = null ) {
		$this->data->add( $data, $templates );

		return $this;
	}

	/**
	 * Get all preassigned template data.
	 *
	 * @param  null|string $template
	 * @return array
	 */
	public function getData( $template = null ) {
		return $this->data->get( $template );
	}

	/**
	 * Register a new template function.
	 *
	 * @param  string   $name
	 * @param  callback $callback
	 * @return Engine
	 */
	public function registerFunction( $name, $callback ) {
		$this->functions->add( $name, $callback );

		return $this;
	}

	/**
	 * Remove a template function.
	 *
	 * @param  string $name
	 * @return Engine
	 */
	public function dropFunction( $name ) {
		$this->functions->remove( $name );

		return $this;
	}

	/**
	 * Get a template function.
	 *
	 * @param  string $name
	 * @return Func
	 */
	public function getFunction( $name ) {
		return $this->functions->get( $name );
	}

	/**
	 * Check if a template function exists.
	 *
	 * @param  string $name
	 * @return boolean
	 */
	public function doesFunctionExist( $name ) {
		return $this->functions->exists( $name );
	}

	/**
	 * Load an extension.
	 *
	 * @param  TemplatesEngineExtensionInterface $extension
	 * @return Engine
	 */
	public function loadExtension( TemplatesEngineExtensionInterface $extension ) {
		$extension->register( $this );

		return $this;
	}

	/**
	 * Load multiple extensions.
	 *
	 * @param  array $extensions
	 * @return Engine
	 */
	public function loadExtensions( array $extensions = array() ) {
		foreach ( $extensions as $extension ) {
			$this->loadExtension( $extension );
		}

		return $this;
	}

	/**
	 * Get a template path.
	 *
	 * @param  string $name
	 * @return string
	 */
	public function path( $name ) {
		$name = new Name( $this, $name );

		return $name->getPath();
	}

	/**
	 * Check if a template exists.
	 *
	 * @param  string $name
	 * @return boolean
	 */
	public function exists( $name ) {
		$name = new Name( $this, $name );

		return $name->doesPathExist();
	}

	/**
	 * Create a new template.
	 *
	 * @param  string $name
	 * @return Template
	 */
	public function make( $name ) {
		return new Template( $this, $name );
	}

	/**
	 * Create a new template and render it.
	 *
	 * @param  string $name
	 * @param  array  $data
	 * @return string
	 */
	public function render( $name, array $data = array() ) {
		return $this->make( $name )->render( $data );
	}

}
