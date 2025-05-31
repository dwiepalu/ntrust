<?php 

namespace Klaravel\Ntrust;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class NtrustServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerNtrust();

        $this->commands($this->commands);

        $this->mergeConfig();
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        // files to publish, dengan tag 'config'
        $this->publishes($this->getPublished(), 'config');

        // Register blade directives
        $this->bladeDirectives();
    }

    /**
     * Register the application bindings.
     *
     * @return void
     */
    private function registerNtrust()
    {
        $this->app->bind('ntrust', function ($app) {
            return new Ntrust($app);
        });

        $this->app->alias('ntrust', Ntrust::class);
    }

    /**
     * The commands provided by the service provider.
     *
     * @var array
     */
    protected $commands = [
        \Klaravel\Ntrust\Commands\MigrationCommand::class,
    ];

    /**
     * Register the blade directives
     *
     * @return void
     */
    private function bladeDirectives()
    {
        Blade::directive('role', function ($expression) {
            return "<?php if (\\Ntrust::hasRole({$expression})) : ?>";
        });

        Blade::directive('endrole', function () {
            return "<?php endif; ?>";
        });

        Blade::directive('permission', function ($expression) {
            return "<?php if (\\Ntrust::can({$expression})) : ?>";
        });

        Blade::directive('endpermission', function () {
            return "<?php endif; ?>";
        });

        Blade::directive('ability', function ($expression) {
            return "<?php if (\\Ntrust::ability({$expression})) : ?>";
        });

        Blade::directive('endability', function () {
            return "<?php endif; ?>";
        });
    }

    /**
     * Get files to be published
     *
     * @return array
     */
    protected function getPublished()
    {
        return [
            realpath(__DIR__.'/../config/ntrust.php') => 
                function_exists('config_path') 
                    ? config_path('ntrust.php') 
                    : base_path('config/ntrust.php'),
        ];
    }

    /**
     * Merges user's and ntrust's configs.
     *
     * @return void
     */
    private function mergeConfig()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/ntrust.php', 'ntrust'
        );
    }
}
