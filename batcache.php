<?php
/*
Plugin name: Batcache Manager (CF Modified)
Plugin URI: https://github.com/crowdfavorite/batcache
Description: This optional plugin improves Batcache.
Author: Andy Skelton (CF Modified)
Author URI: http://andyskelton.com/
Version: 0.1
 */

/**
 * CF_Batcache_Manager
 */
class CF_Batcache_Manager {

	private $_batcache;

	/**
	 * Constructor
	 *
	 * @param $bc Batcache global instance
	 * @access public
	 */
	public function __construct($bc) {
		global $wp_object_cache;
		$this->_batcache = $bc;
		$this->_batcache->configure_groups();
	}

	/**
	 * Adds WordPress Actions/Filters if batcache is enabled
	 *
	 * @access public
	 * @return void
	 */
	public function add_hooks() {
		if ($this->_batcache) {
			add_action('wp_loaded', array($this, 'wp_loaded'));
			add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 99);
			add_action('clean_post_cache', array($this, 'clean_post_cache'));
		}
	}

	/* Hooks */

	/**
	 * Triggers a global cache flush if 'flush_cache' GET param is set to 'true'
	 *
	 * Also checks for super admin privilege and on an admin screen
	 *
	 * @wp-action wp_loaded
	 * @access public
	 * @return void
	 */
	public function wp_loaded() {
		if (is_super_admin() && is_admin()) {
			if (isset($_GET['flush_cache']) && $_GET['flush_cache'] == 'true') {
				if($this->flush_all()) {
					add_action('admin_notices', create_function('', 'echo "<div class=\"updated\"><p>Cache flushed</p></div>";'));
				}
				else {
					add_action('admin_notices', create_function('', 'echo "<div class=\"error\"><p>There was a problem in trying to flush the cache.</p></div>";'));
				}
			}
		}
	}

	/**
	 * Adds the "Flush Page Cache" button to the admin bar
	 *
	 * @wp-action admin_bar_menu
	 * @access public
	 * @return void
	 */
	public function admin_bar_menu() {
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

	/**
	 * Clears individual post cache and home page cache when post is added/updated
	 *
	 * @param int $post_id  ID of post being saved
	 * @access public
	 * @return void
	 */
	public function clean_post_cache($post_id) {
		$this->clear_post($post_id);
		$this->clear_home();
	}

	/* Utility Functions */

	/**
	 * Attempt to flush all caches
	 *
	 * @access public
	 * @return bool
	 */
	public function flush_all() {
		if (!wp_cache_flush()) {
			global $wp_object_cache;
			if ($wp_object_cache instanceof APC_Object_Cache) {
				$this->cache = array();
				return apc_clear_cache('user');
			}
			else { // Assume memcache backend
				$ret = true;
				foreach (array_keys($wp_object_cache->mc) as $group) {
					$ret &= $wp_object_cache->mc[$group]->flush();
				}
				return $ret;
			}
		}
		return true;
	}

	/**
	 * Clear the home page cache
	 *
	 * @access public
	 * @return void
	 */
	public function clear_home() {
		$this->clear_url(get_option('home'));
		$this->clear_url(trailingslashit(get_option('home')));
	}

	/**
	 * Clear cache for a specified post_id
	 *
	 * @param int $post_id  The post_id to clear
	 * @access public
	 * @return void
	 */
	public function clear_post($post_id) {
		$post = get_post($post_id);
		if ($post->post_type != 'revision' || get_post_status($post_id) == 'publish') {
			$this->clear_url(get_permalink($post_id));
		}
	}

	/**
	 * Clear cache for a specified url
	 *
	 * @param string $url  url to clear
	 * @access public
	 * @return void
	 */
	public function clear_url($url) {
		if (!$url) {
			return false;
		}
		$url_key = md5($url);
		wp_cache_add("{$url_key}_version", 0, $this->_batcache->group);
		return wp_cache_incr("{$url_key}_version", 1, $this->_batcache->group);
	}
}

// Do not load if our advanced-cache.php isn't loaded
if ( isset($batcache) && is_object($batcache) && method_exists($wp_object_cache, 'incr')) {
	$cf_batcache = new CF_Batcache_Manager($batcache);
	$cf_batcache->add_hooks();
}
