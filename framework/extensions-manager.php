<?php

namespace Blocksy;

class ExtensionsManager {
	/**
	 * Collection of all the activated extensions.
	 *
	 * @var array The array of all the extension objects.
	 */
	private $extensions = [];

	private function get_option_name() {
		return 'blocksy_active_extensions';
	}

	/**
	 * Collect all available extensions and activate the ones that have to be so.
	 */
	public function __construct() {
		$this->read_installed_extensions();

		if ($this->is_dashboard_page()) {
			$this->do_extensions_preboot();
		}

		foreach ($this->get_activated_extensions() as $single_id) {
			$this->boot_activated_extension_for($single_id);
		}
	}

	public function do_extensions_preboot() {
		foreach (array_keys($this->get_extensions()) as $single_id) {
			$this->maybe_do_extension_preboot($single_id);
		}
	}

	private function is_dashboard_page() {
		global $pagenow;

		$is_ct_settings =
			// 'themes.php' === $pagenow &&
			isset( $_GET['page'] ) && 'ct-dashboard' === $_GET['page'];

		return $is_ct_settings;
	}

	public function get_extensions() {
		return $this->extensions;
	}

	public function can( $capability = 'install_plugins' ) {
		if ( is_multisite() ) {
			// Only network admin can change files that affects the entire network.
			$can = current_user_can_for_blog( get_current_blog_id(), $capability );
		} else {
			$can = current_user_can( $capability );
		}

		if ( $can ) {
			// Also you can use this method to get the capability.
			$can = $capability;
		}

		return $can;
	}

	public function activate_extension($id) {
		if (! isset($this->extensions[$id])) {
			return;
		}

		$activated = $this->get_activated_extensions();
		$activated[] = strtolower($id);

		update_option($this->get_option_name(), array_unique($activated));
	}

	public function deactivate_extension($id) {
		if (! isset($this->extensions[$id])) {
			return;
		}

		update_option($this->get_option_name(), array_diff(
			$this->get_activated_extensions(),
			[ $id ]
		));
	}

	private function read_installed_extensions() {
		$paths_to_look_for_extensions = [
			BLOCKSY_PATH . 'framework/extensions'
		];

		foreach ($paths_to_look_for_extensions as $single_path) {
			$all_extensions = glob($single_path . '/*', GLOB_ONLYDIR);

			foreach ($all_extensions as $single_extension) {
				$this->register_extension_for($single_extension);
			}
		}
	}

	private function register_extension_for($path) {
		$id = str_replace('_', '-', basename($path));

		if (isset($this->extensions[$id])) return;

		$this->extensions[$id] = [
			'path' => $path,
			'__object' => null,
			'config' => $this->read_config_for($path),
			'readme' => $this->read_readme_for($path),
			'data' => null
		];

		// if (method_exists())
	}

	private function maybe_do_extension_preboot($id) {
		if (! isset($this->extensions[$id])) return false;
		if (isset($this->extensions[$id]['__object_preboot'])) return;

		$class_name = explode( '-', $id );
		$class_name = array_map( 'ucfirst', $class_name );
		$class_name = 'BlocksyExtension' . implode( '', $class_name ) . 'PreBoot';

		$path = $this->extensions[$id]['path'];

		if (!file_exists($path . '/pre-boot.php')) {
			return;
		}

		require_once($path . '/pre-boot.php');

		$this->extensions[$id]['__object_preboot'] = new $class_name();

		if (method_exists(
			$this->extensions[$id]['__object_preboot'], 'ext_data'
		)) {
			$this->extensions[$id]['data'] = $this->extensions[
				$id
			]['__object_preboot']->ext_data();
		}
	}

	private function boot_activated_extension_for($id) {
		if (! isset($this->extensions[$id])) return false;
		if (isset($this->extensions[$id]['__object'])) return;

		$class_name = explode( '-', $id );
		$class_name = array_map( 'ucfirst', $class_name );
		$class_name = 'BlocksyExtension' . implode( '', $class_name );

		$path = $this->extensions[$id]['path'];

		require_once($path . '/extension.php');

		$this->extensions[$id]['__object'] = new $class_name();
	}

	private function read_readme_for($path) {
		$readme = '';

		ob_start();

		if (is_readable($path . '/readme.php')) {
			require $path . '/readme.php';
		}

		$readme = ob_get_clean();

		if (empty(trim($readme))) {
			return null;
		}

		return trim($readme);
	}

	private function read_config_for( $file_path ) {
		$_extract_variables = [ 'config' => [] ];

		if (is_readable($file_path . '/config.php')) {
			require $file_path . '/config.php';

			foreach ( $_extract_variables as $variable_name => $default_value ) {
				if ( isset( $$variable_name ) ) {
					$_extract_variables[ $variable_name ] = $$variable_name;
				}
			}
		}

		$name = explode( '-', basename($file_path) );
		$name = array_map( 'ucfirst', $name );
		$name = implode( ' ', $name );

		$_extract_variables['config'] = array_merge(
			[
				'name' => $name,
				'description' => ''
			],
			$_extract_variables['config']
		);

		return $_extract_variables['config'];
	}

	private function get_activated_extensions() {
		return get_option($this->get_option_name(), []);
	}
}

