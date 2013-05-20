<?php
namespace ActiveRecord;
use Closure;

/**
 * Cache::get('the-cache-key', function() {
 *	 # this gets executed when cache is stale
 *	 return "your cacheable datas";
 * });
 */
class Cache
{
	static $adapter = null;
	static $options = array();
	static $cache_table = array();

	/**
	 * Initializes the cache.
	 *
	 * With the $options array it's possible to define:
	 * - expiration of the key, (time in seconds)
	 * - a namespace for the key
	 *
	 * this last one is useful in the case two applications use
	 * a shared key/store (for instance a shared Memcached db)
	 *
	 * Ex:
	 * $cfg_ar = ActiveRecord\Config::instance();
	 * $cfg_ar->set_cache('memcache://localhost:11211',array('namespace' => 'my_cool_app',
	 *																											 'expire'		 => 120
	 *																											 ));
	 *
	 * In the example above all the keys expire after 120 seconds, and the
	 * all get a postfix 'my_cool_app'.
	 *
	 * (Note: expiring needs to be implemented in your cache store.)
	 *
	 * @param string $url URL to your cache server
	 * @param array $options Specify additional options
	 */
	public static function initialize($url, $options=array())
	{
		try
		{
			if ($url)
			{
				$url = parse_url($url);
				$file = ucwords(Inflector::instance()->camelize($url['scheme']));
				$class = "ActiveRecord\\$file";
				require_once __DIR__ . "/cache/$file.php";
				static::$adapter = new $class($url);
			}
			else
				static::$adapter = null;
		}
		catch (CacheException $e)
		{
			static::$adapter = null;
		}

		static::$options = array_merge(array('expire' => 30, 'namespace' => ''),$options);
	}

	public static function flush()
	{
		if (static::$adapter)
			static::$adapter->flush();
	}

	public static function get($key, $closure)
	{
		$key = static::get_namespace() . $key;
		
		if (!static::$adapter)
			return $closure();

		if (!($value = static::$adapter->read($key)))
			static::$adapter->write($key,($value = $closure()),static::$options['expire']);

		return $value;
	}

	private static function get_namespace()
	{
		return (isset(static::$options['namespace']) && strlen(static::$options['namespace']) > 0) ? (static::$options['namespace'] . "::") : "";
	}

	public static function set_cache($table)
	{
		static::$cache_table[$table] = true;
	}

	public static function is_enable_cache($sql)
	{
		$values = array();

		if (preg_match('/.*\s*(FROM|INTO|UPDATE)\s+\`*(\w+\.)?(\w+)\`*.*/', $sql, $values))
			return static::is_enable_cache_for_table($values[3]);

		return false;
	}

	public static function is_enable_cache_for_table($table)
	{
		if (isset(static::$cache_table[$table]))
			return static::$cache_table[$table];

		return false;
	}
}
?>
