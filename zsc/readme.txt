=== ZSC Zeumic Suite Common ===
Contributors: zeumic
Requires at least: 4.4
Tested up to: 4.8
License: GPLv2

ZSC Zeumic Suite Common, a shared library used by Zeumic plugins.

== Changelog ==

ZSC uses semantic versioning.

= 11.0.2 =

* Release date: November 14, 2017

* Bugfix [Installation]: "The plugin does not have a valid header." errors when installing plugins which include ZPC.
* Bugfix [Updating]: Incorrect version numbers when updating plugins which include ZPC.

= 11.0.1 =

* Release date: September 25, 2017

* Bugfix [bound_select]: Fix bug where editTemplate/insertTemplates weren't working if non-editing/inserting field.

= 11.0.0 =

* Release date: August 6, 2017

* Third-party [Chosen]: Removed Chosen jQuery extension.
* Third-party [Select2]: Added Select2 jQuery extension.
* Third-party [Select2]: Added zsc.select2Sortable() to allow Select2 to be made sortable through jQuery UI Sortable.
* Refactor [multiselect]: Rewrote to use Select2 and be much more extensible.
* Refactor [multiselect_user]: Rewrote to use multiselect.

= 10.2.0 =

* Release date: June 25, 2017

* New [util] zsc.enableAutosearch();
* New [util] zsc.isUrl().

= 10.1.0 =

* Release date: June 17, 2017

* New [Fields]: Added basic support for secret fields, which are always sent to jsGrid but are hidden from the user.
* Display [TriCheckbox]: Unchecked checkboxes no longer red, default is now grey.
* Bugfix [TriCheckbox]: onUpdate no longer called when checkbox is initialized.
* Bugfix [PluginLoader]: Dependency checking failed when required version was specified in full (M.m.p) and was the same as the version found.

= 10.0.0 =

* Release date: June 16, 2017

* New [util] array_match_structure().
* New [util] array_deep_merge().
* Refactor: Changed entire JS API to use camel-case.
* Refactor: Moved external libraries to vendor/ directory.
* Refactor [style.css]: Replaced .zsuite* with .zsc*.
* Refactor [zsc.MetaEditor]: checkboxIndeterminate replaced with checkboxDefault.
* Refactor [zsc.MetaEditor]: Advanced checkbox adding ability removed, too convoluted.
* Refactor [zsc.Checkbox]: Replaced with $.fn.zscTriCheckbox.
* Bugfix [zsc.MetaEditor]: checkboxIndeterminate/Default wasn't working.
* Bugfix [PluginLoader]: Dependencies weren't being enforced.
* Bugfix [PluginLoader]: Failed optional dependencies were being enforced when they shouldn't have been.

= 9.0.0 =

* Release date: June 13, 2017

* Stability: Version registration and loading system for self, so latest version of ZSC is always loaded.
* Stability: Won't load self or any registered plugins if PHP version < 5.3.
* Refactor: Namespaces are now used.
* Refactor: Split out ZSC.php into many smaller, more modular files.
* Refactor [util]: Utility functions file: util.php.
* New [util]: New utility functions.
* Refactor [Ajax]: New class for AJAX handling.
* New [PluginLoader]: New class implementing plugin registration and loading system.
* Refactor [Resources]: New class for resource handling.
* New [Settings]: Type select and bool now have 'default' as an option.
* New [Settings]: Added types float, select_int and select_float.
* Prune [Settings]: Removed type natural_num.

= 8.1.0 =

* Release date: May 31, 2017

* zsc_order custom field is more lenient with its input.
* Removed lots of unnecessary code from select_user custom field.

= 8.0.0 =

* Release date: May 31, 2017

* Added ZSC_Fields.
* Renamed ZSuite_* to ZSC_*.
* Added zsc_url custom field.
* Bugfix: Loader wouldn't disappear on AJAX error.

= 7.4.0 =

* Release date: May 30, 2017

* zsc.Plugin.init won't try to load grid if settings.field not provided.
* Moved bulk of ajax() and ajax_error() to util.

= 7.3.1 =

* Release date: May 25, 2017

* From email/name fixed.
* Fixed displayed version and improved instructions for error notice when core/ext versions differ.

= 7.3.0 =

* Release date: May 23, 2017

* Added ZSC_Plugin_Pro under pro/, which Pro extensions should inherit from.
* ZSuite_Plugin::plugin_update() now calls only when the version number has actually changed.

= 7.2.0 =

* Release date: May 22, 2017

* Added zsc.Checkbox, which supports indeterminate.
* MetaEditor now supports idField, and entries() can accept keyPrev arg. Also supports indeterminate checkboxes.

= 7.1.0 =

* Release date: May 19, 2017

* Added ZSuite_Plugin::zsc_order_add_product_meta().
* Added zsc.product_get() to draw from zsc.meta.product.
* Changed zsc_order field to use zsc.product_get() if possible.
* Extension plugin tickers now use _ instead of - (e.g. zwm_pro rather than zwm-pro).

= 7.0.1 =

* Release date: May 19, 2017

* numRows wasn't working, was always showing 20 rows
* Current filter/search was removed after updating/deleting/etc.

= 7.0.0 =

* Release date: May 18, 2017

* Created zsc object/module, which now contains all util functions.
* Created zsc.Plugin, which child plugins now use/inherit from in their JS files.
* Fixed jsGrid editValue() object/array merging issue (by modifying jsGrid.Grid.prototype).
* Added construct_succeeded prop to ZSuite_Plugin, which ZSuite_Plugin_Ext checks during construction.
* Made meta editor OO/prototype-based rather than functional, with zsc.MetaEditor.
* Changed ZSuite_Plugin_Ext::__construct() API to take in core ticker rather than object.
* Added ZSuite_Plugin_Ext::core_init_before().
* Merged zsc-pro files into setup.php, replaced zsuite_pro_plugin_load() with zsuite_load_ext().
* New conditional enqueue system, supported by ZSuite_Plugin::enqueue_custom_field_types().
* Changed 'zsuite' style and script handles to 'zsc'.

= 6.0.0 =

* Release date: May 11, 2017

* Added ZSuite_Plugin_Core and ZSuite_Plugin_Ext, with version-safe verification code in constructors.
* Completely new API for __construct(), new construct_success().
* ZSuite_Settings added for settings management.
* Fixed infinite zsuite_staff keys on same user.
* Overrode jsGrid field default itemTemplate to escape HTML.

= 5.1.0 =

* Release date: May 10, 2017

* Fixed default settings not being applied properly (ZSuite_Plugin::get_setting).
* Fixed versioning logic breaking on front-end.

= 5.0.0 =

* Release date: May 8, 2017

* Restructured for free/Pro separation refactoring

= 4.3.0 =

* Release date: May 2, 2017

* Added onChange to zsuite_bound_select

= 4.2.0 =

* Release date: April 28, 2017

* New jsGrid field zsuite_bound_select
* New functions zsuite_add_meta_queries, zsuite_get_meta, zsuite_set_meta, zsuite_update_meta, zsuite_watch, zsuite_eval
* New functions zsuite_popup_menu, zsuite_popup_menu_add_option

= 4.1.0 =

* Release date: April 21, 2017

* Added functions and CSS for meta editor

= 4.0.0 =

* Release date: April 13, 2017

* Added zsuite_order as custom jsGrid field
* Changed AJAX functions
* Added debug()
* Added zsuite_add_refresh_btn()
* Added refresh button CSS

= 3.0.0 =

* Release date: April 12, 2017

* Added ZSuite_Plugin class
* Changed selectuser field for jsGrid to take a variable name as its items prop
* Added zsuite_user()

= 2.1.0 =

* Release date: April 5, 2017

* Added min-height: 200px for edit row textareas
* Added zsuite_maintain_sorting()

= 2.0.0 =

* Release date: April 4, 2017

* Namespaced most styles under .zsuite
* Added Chosen override

= 1.3.1 =

* Release date: March 30, 2017

* Fixed zsuite_on_create and zsuite_on_remove to handle broken jQuery find()

= 1.3.0 =

* Release date: March 27, 2017

* Released as individual repository
