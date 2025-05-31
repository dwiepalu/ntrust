<?php namespace Klaravel\Ntrust\Traits;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

trait NtrustPermissionTrait
{
    /**
     * Many-to-Many relations with role model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(Config::get('ntrust.profiles.' . self::$roleProfile . '.role'), 
            Config::get('ntrust.profiles.' . self::$roleProfile . '.permission_role_table'),
            Config::get('ntrust.profiles.' . self::$roleProfile . '.permission_foreign_key'),
            Config::get('ntrust.profiles.' . self::$roleProfile . '.role_foreign_key')
            );
    }

    /**
     * Trait boot method
     * 
     * @return void
     */
    protected static function bootNtrustPermissionTrait()
	{
    static::deleted(function ($permission) {
        // Flush cache jika cache store mendukung tagging
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(Config::get('ntrust.profiles.' . self::$roleProfile . '.permission'))->flush();
        }
        // Lepas semua relasi roles meski cache tidak pakai tagging
        $permission->roles()->sync([]);
    });
	}
}
