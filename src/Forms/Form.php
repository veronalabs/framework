<?php // phpcs:ignore WordPress.Files.FileName
/**
 * Backyard base form class.
 *
 * @package   backyard-framework
 * @author    Sematico LTD <hello@sematico.com>
 * @copyright 2020 Sematico LTD
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://sematico.com
 */

namespace Backyard\Forms;

use Laminas\Form\Form as LaminasForm;
use Laminas\View\Renderer\PhpRenderer;
use Backyard\Contracts\FormRendererInterface;
use Backyard\Exceptions\MissingConfigurationException;
use Backyard\Forms\Elements\Nonce;
use Backyard\Forms\Filters\SanitizeTextarea;
use Backyard\Forms\Filters\SanitizeTextField;
use Backyard\Forms\Renderers\CustomFormRenderer;
use Backyard\Forms\Renderers\NonceFieldRenderer;
use Backyard\Utils\RequestFactory;
use Laminas\Form\ConfigProvider;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Textarea;

/**
 * Backyard forms builder.
 */
abstract class Form extends LaminasForm {

	/**
	 * List of tabs defined for the form.
	 *
	 * @var array
	 */
	protected $tabs = [];

	/**
	 * Laminas php rendering engine.
	 *
	 * @var PhpRenderer|\Laminas\Form\View\HelperTrait|null
	 */
	protected $renderer;

	/**
	 * Custom Backyard framework form rendering layout.
	 *
	 * @var FormRendererInterface|null
	 */
	protected $customRenderer;

	/**
	 * Setup the form.
	 *
	 * @param string $name
	 * @param array  $options
	 */
	public function __construct( $name = null, $options = [] ) {
		parent::__construct( $name, $options );

		if ( empty( $this->getOption( 'nonce_name' ) ) ) {
			$this->setOption( 'nonce_name', $name );
		}

		$this->setupFields();
		$this->registerFields();
		$this->setupSanitizationFilters();
		$this->setupNonceField();
	}

	/**
	 * Configure fields within the form.
	 *
	 * @return void
	 */
	abstract public function setupFields();

	/**
	 * Verify fields have been properly configured.
	 *
	 * - When the form has tabs, all fields must have a tab assigned. Except the submit button.
	 *
	 * @throws MissingConfigurationException When the form has tabs and some fields have no tab assigned.
	 * @return void
	 */
	protected function registerFields() {

		if ( ! $this->hasTabs() ) {
			return;
		}

		foreach ( $this->getTabs() as $key => $tabConfig ) {

			$tabFields = $tabConfig['fields'];

			foreach ( $tabFields as $tabField ) {
				$tabField['options']['tab'] = $key;
				$this->add( $tabField );
			}
		}

		// Determine if there's fields with no tab assigned.
		/** @var \Laminas\Form\Element $field */
		foreach ( $this as $field ) {

			if ( $field instanceof Submit ) {
				continue;
			}

			if ( ! $field->getOption( 'tab' ) ) {
				throw new MissingConfigurationException( sprintf( 'Field "%s" requires a tab option.', $field->getName() ) );
			}
		}
	}

	/**
	 * Add default sanitization filters to inputs based on their type.
	 *
	 * @return void
	 */
	private function setupSanitizationFilters() {

		$filters = $this->getInputFilter();

		/** @var \Laminas\Form\Element $field */
		foreach ( $this as $field ) {

			if ( $field instanceof Textarea ) {
				$filters->add(
					[
						'name'    => $field->getName(),
						'filters' => [
							[ 'name' => SanitizeTextarea::class ],
						],
					]
				);
			} else {
				$filters->add(
					[
						'name'    => $field->getName(),
						'filters' => [
							[ 'name' => SanitizeTextField::class ],
						],
					]
				);
			}
		}

	}

	/**
	 * Automatically add a nonce field to the form.
	 *
	 * @throws MissingConfigurationException When nonce_name option is missing from the form.
	 * @return void
	 */
	private function setupNonceField() {

		if ( empty( $this->getOption( 'nonce_name' ) ) ) {
			throw new MissingConfigurationException( 'Every form requires a nonce_name option.' );
		}

		$nonceInput = new Nonce( $this->getOption( 'nonce_name' ) );

		$this->add( $nonceInput );

	}

	/**
	 * Determine if the form has tabs.
	 *
	 * @return boolean
	 */
	public function hasTabs() {
		return ! empty( $this->tabs );
	}

	/**
	 * Get the list of tabs for the form.
	 *
	 * @return array
	 */
	public function getTabs() {
		return $this->tabs;
	}

	/**
	 * Add a single tab to the form.
	 *
	 * @param string $id
	 * @param string $label
	 * @param array  $fields
	 * @return Form
	 */
	public function addTab( string $id, string $label, array $fields ) {
		$this->tabs[ $id ] = [
			'label'  => $label,
			'fields' => $fields,
		];
		return $this;
	}

	/**
	 * Add tabs to the form.
	 *
	 * @param array $tabs
	 * @return Form
	 */
	public function addTabs( array $tabs ) {
		$this->tabs = $tabs;
		return $this;
	}

	/**
	 * Return the key of the currently active form tab.
	 * If the form has tabs, but no tab is detected,
	 * it'll default back to the 1st tab.
	 *
	 * @return bool|string
	 */
	public function getActiveTab() {

		if ( ! $this->hasTabs() ) {
			return;
		}

		$tabs        = $this->getTabs();
		$queryParams = RequestFactory::getQueryParams();

		if ( $queryParams->has( 'tab' ) && array_key_exists( $queryParams->get( 'tab' ), $tabs ) ) {
			return $queryParams->get( 'tab' );
		} else {
			return key( $tabs );
		}

	}

	/**
	 * Set a custom form layout renderer.
	 *
	 * @param string $renderer class path to custom render.
	 * @return void
	 */
	public function setCustomRenderer( string $renderer ) {
		if ( ! $this->renderer ) {
			$this->makeRenderer();
		}
		$this->customRenderer = new $renderer( $this );
	}

	/**
	 * Get the Laminas php rendering engine.
	 *
	 * @return PhpRenderer|null
	 */
	public function getRenderer() {
		return $this->renderer;
	}

	/**
	 * Get the custom rendering object.
	 *
	 * @return FormRendererInterface|null
	 */
	public function getCustomRenderer() {
		return $this->customRenderer;
	}

	/**
	 * Setup the Laminas rendering engine.
	 *
	 * @return void
	 */
	public function makeRenderer() {

		/** @var PhpRenderer|\Laminas\Form\View\HelperTrait $renderer */
		$renderer = new PhpRenderer();

		$renderer->getHelperPluginManager()->configure(
			( new ConfigProvider() )->getViewHelperConfig()
		);

		// Setup rendering of custom elements.
		$renderer->formElement()->addClass( Nonce::class, NonceFieldRenderer::class );

		$this->renderer = $renderer;
	}

	/**
	 * Render the form.
	 *
	 * @return string
	 */
	public function render() {

		$output = false;

		try {
			if ( ! $this->customRenderer ) {
				$this->makeRenderer();
				$output = $this->renderer->form( $this );
			} elseif ( $this->customRenderer instanceof CustomFormRenderer ) {
				$output = $this->customRenderer->render();
			}
		} catch ( \Throwable $th ) {
			wp_die( $th->getMessage() ); //phpcs:ignore
		}

		return $output;

	}

}
