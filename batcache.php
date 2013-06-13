<?php
/*
Plugin name: Batcache Manager (CF Modified)
Plugin URI: http://wordpress.org/extend/plugins/batcache/
Description: This optional plugin improves Batcache.
Author: Andy Skelton (CF Modified)
Author URI: http://andyskelton.com/
Version: 0.1
*/

class CF_Batcache_Manager {

	private $_batcache;

	public function __construct($bc) {
		global $wp_object_cache;
		// Do not load if our advanced-cache.php isn't loaded
		if ( ! is_object($bc) || ! method_exists( $wp_object_cache, 'incr' ) ) {
			return;
		}

		$this->_batcache = $bc;
		$this->_batcache->configure_groups();
	}

	public function add_hooks() {
		if ($this->_batcache) {
			add_action('wp_loaded', array($this, 'wp_loaded'));
			add_action('admin_bar_menu', array($this, 'admin_bar'), 99);
			add_action('clean_post_cache', array($this, 'clear_post'));
		}
	}

	public function wp_loaded() {
		if (is_super_admin() && is_admin()) {
			if (isset($_GET['flush_cache']) && $_GET['flush_cache'] == 'true') {
				$this->flush_all();
				add_action('admin_notices', create_function('', 'echo "<div class=\"updated\"><p>Cache flushed</p></div>";'));
			}
		}
	}

	public function admin_bar() {
		global $wp_admin_bar;
		if ($this->_batcache && is_super_admin() && is_admin()) {
			$query_string = (is_array($wp->query_string)) ? $wp->query_string : array();
			$wp_admin_bar->add_menu(array(
				'id' => 'batcache_flush',
				'title' => __('Flush Page Cache'),
				'href' => add_query_arg('flush_cache', 'true', $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]),
				'meta' => array('onclick' => 'return confirm("Are you sure you want to invalidate caches for the entire site?");'),
			));
		}
	}

	public function flush_all() {
		global $wp_object_cache;
		$ret = true;
		foreach ( array_keys($wp_object_cache->mc) as $group )
			$ret &= $wp_object_cache->mc[$group]->flush();
		return $ret;
	}

	public function clear_home() {
		$this->clear_url( get_option('home') );
		$this->clear_url( trailingslashit( get_option('home') ) );
	}

	public function clear_post($post_id) {
		$post = get_post($post_id);
		if ($post->post_type != 'revision' || get_post_status($post_id) == 'publish') {
			$this->clear_url(get_permalink($post_id));
			$this->clear_home();
		}
	}

	private function clear_url($url) {
		if (!$url) {
			return false;
		}
		$url_key = md5($url);
		wp_cache_add("{$url_key}_version", 0, $this->_batcache->group);
		return wp_cache_incr("{$url_key}_version", 1, $this->_batcache->group);
	}
}

$cf_batcache = new CF_Batcache_Manager($batcache);
$cf_batcache->add_hooks();
