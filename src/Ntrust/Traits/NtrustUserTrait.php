<?php
namespace Klaravel\Ntrust\Traits;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait NtrustUserTrait
{
    //Big block of caching functionality.
    public function cachedRoles()
    {
        $userPrimaryKey = $this->primaryKey;
        $cacheKey = 'ntrust_roles_for_' . self::$roleProfile . '_' . $this->$userPrimaryKey;

        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags(Config::get('ntrust.profiles.' . self::$roleProfile . '.role_user_table'))
                ->remember($cacheKey, Config::get('cache.ttl', 1440), function () {
                    return $this->roles()->get();
                });
        }

        return $this->roles()->get();
    }

    /**
     * Many-to-Many relations with Role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(
            Config::get('ntrust.profiles.' . self::$roleProfile . '.role'),
            Config::get('ntrust.profiles.' . self::$roleProfile . '.role_user_table'),
            Config::get('ntrust.profiles.' . self::$roleProfile . '.user_foreign_key'),
            Config::get('ntrust.profiles.' . self::$roleProfile . '.role_foreign_key')
        );
    }

    /**
     * Trait boot method
     *
     * @return void
     */
    protected static function bootNtrustUserTrait()
    {
        static::saved(function () {
            self::clearCache();
        });

        static::deleted(function ($user) {
            self::clearCache($user);
        });

        if (method_exists(self::class, 'restored')) {
            static::restored(function ($user) {
                self::clearCache($user);
            });
        }
    }

    /**
     * Checks if the user has a role by its name.
     *
     * @param string|array $name       Role name or array of role names.
     * @param bool         $requireAll All roles in the array are required.
     *
     * @return bool
     */
    public function hasRole($name, $requireAll = false)
    {
        if (is_array($name)) {
            foreach ($name as $roleName) {
                $hasRole = $this->hasRole($roleName);

                if ($hasRole && !$requireAll) {
                    return true;
                } elseif (!$hasRole && $requireAll) {
                    return false;
                }
            }

            return $requireAll;
        } else {
            foreach ($this->cachedRoles() as $role) {
                if ($role->name === $name) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user has a permission by its name.
     *
     * @param string|array $permission Permission string or array of permissions.
     * @param bool         $requireAll All permissions in the array are required.
     *
     * @return bool
     */
    public function can($permission, $requireAll = false)
    {
        if (is_array($permission)) {
            foreach ($permission as $permName) {
                $hasPerm = $this->can($permName);

                if ($hasPerm && !$requireAll) {
                    return true;
                } elseif (!$hasPerm && $requireAll) {
                    return false;
                }
            }

            return $requireAll;
        } else {
            foreach ($this->cachedRoles() as $role) {
                // Validate against the Permission table
                foreach ($role->cachedPermissions() as $perm) {
                    if (Str::is($permission, $perm->name)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Checks role(s) and permission(s).
     *
     * @param string|array $roles       Array of roles or comma separated string
     * @param string|array $permissions Array of permissions or comma separated string.
     * @param array        $options     validate_all (true|false) or return_type (boolean|array|both)
     *
     * @throws \InvalidArgumentException
     *
     * @return array|bool
     */
    public function ability($roles, $permissions, $options = [])
    {
        if (!is_array($roles)) {
            $roles = explode(',', $roles);
        }
        if (!is_array($permissions)) {
            $permissions = explode(',', $permissions);
        }

        $options['validate_all'] = $options['validate_all'] ?? false;
        if (!in_array($options['validate_all'], [true, false], true)) {
            throw new InvalidArgumentException('validate_all must be boolean');
        }

        $options['return_type'] = $options['return_type'] ?? 'boolean';
        if (!in_array($options['return_type'], ['boolean', 'array', 'both'], true)) {
            throw new InvalidArgumentException('return_type must be boolean, array or both');
        }

        $checkedRoles = [];
        $checkedPermissions = [];

        foreach ($roles as $role) {
            $checkedRoles[$role] = $this->hasRole($role);
        }

        foreach ($permissions as $permission) {
            $checkedPermissions[$permission] = $this->can($permission);
        }

        $validateAll = false;
        if ($options['validate_all']) {
            $validateAll = !in_array(false, $checkedRoles, true) && !in_array(false, $checkedPermissions, true);
        } else {
            $validateAll = in_array(true, $checkedRoles, true) || in_array(true, $checkedPermissions, true);
        }

        switch ($options['return_type']) {
            case 'boolean':
                return $validateAll;
            case 'array':
                return ['roles' => $checkedRoles, 'permissions' => $checkedPermissions];
            case 'both':
                return [$validateAll, ['roles' => $checkedRoles, 'permissions' => $checkedPermissions]];
        }

        // fallback, should never reach here
        return $validateAll;
    }

    /**
     * Alias to eloquent many-to-many relation's attach() method.
     *
     * @param mixed $role
     */
    public function attachRole($role)
    {
        if (is_object($role)) {
            $role = $role->getKey();
        }

        if (is_array($role)) {
            $role = $role['id'];
        }

        $this->roles()->attach($role);

        self::clearCache();
    }

    /**
     * Alias to eloquent many-to-many relation's detach() method.
     *
     * @param mixed $role
     */
    public function detachRole($role)
    {
        if (is_object($role)) {
            $role = $role->getKey();
        }

        if (is_array($role)) {
            $role = $role['id'];
        }

        $this->roles()->detach($role);

        self::clearCache();
    }

    /**
     * Attach multiple roles to a user
     *
     * @param mixed $roles
     */
    public function attachRoles($roles)
    {
        foreach ($roles as $role) {
            $this->attachRole($role);
        }
    }

    /**
     * Detach multiple roles from a user
     *
     * @param mixed|null $roles
     */
    public function detachRoles($roles = null)
    {
        if (!$roles) {
            $roles = $this->roles()->get();
        }

        foreach ($roles as $role) {
            $this->detachRole($role);
        }
    }

    /**
     * Clear cache
     *
     * @param mixed|null $user
     */
    public static function clearCache($user = null)
    {
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(Config::get('ntrust.profiles.' . self::$roleProfile . '.role_user_table'))->flush();

            if ($user) {
                // Hati-hati, ini akan menghapus semua roles user
                $user->roles()->sync([]);
            }
        }
    }
}
