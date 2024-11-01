<?php
namespace Zeumic\ZSC\Core;

abstract class PluginCore extends PluginDistributable {
	### BEGIN OVERRIDES ###

	public function plugin_row_meta($links, $file) {
		if (strpos($file, basename($this->pl_file())) !== false) {
			$links = parent::plugin_row_meta($links, $file);
			$links['pro'] = '<a href="https://www.zeumic.com.au/product/'.$this->pl_slug('pro').'/" target="_blank"><span class="dashicons dashicons-star-filled"></span> Free upgrade to '.$this->pl_name('Pro').'</a>';
		}
		return $links;
	}

	public function init() {
		do_action($this->pf('init_before'));
		parent::init();
	}

	public function admin_init() {
		parent::admin_init();
		if (!Plugin::has_plugin($this->pl_ticker('pro'))) {
			add_action('admin_notices', function() {
				?><div class="notice notice-info"><p>Enjoying the free version? Upgrade now to <a href="https://www.zeumic.com.au/product/<?php echo $this->pl_slug('pro');?>"><?php echo $this->pl_name('Pro');?></a> for <b>FREE</b>!</p></div><?php
			});
		}
	}

	### END OVERRIDES ###
}
