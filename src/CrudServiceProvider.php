<?php

namespace Infinety\CRUD;

use Illuminate\Console\DetectsApplicationNamespace;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Infinety\CRUD\Commands\CrudCreatorHelper;
use Infinety\CRUD\Commands\CrudCreatorHelperInline;
use Infinety\CRUD\Commands\CrudInstaller;
use Route;
use Storage;

class CrudServiceProvider extends ServiceProvider
{
    use DetectsApplicationNamespace;

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Perform post-registration booting of services.
     */
    public function boot()
    {
        // use this if your package has views
        $this->loadViewsFrom(realpath(__DIR__.'/resources/views'), 'crud');

        /*
         * Publish Layout view
         */
        $this->publishes([
            __DIR__.'/resources/views/layout' => base_path('resources/views/layouts'),
        ], 'layout');

        // Publish views
        $this->publishes([__DIR__.'/resources/views/default_pages' => resource_path('views/vendor/infinety/crud'),
        ]);

        // Publish Listeners
        $this->publishes([__DIR__.'/Listeners' => app_path('Listeners'),
        ]);

        // Setup Routes
        $this->setupRoutes($this->app->router);

        /*
         * Config File
         */
        $this->publishes([
            __DIR__.'/../config/infinety-crud.php' => config_path('infinety-crud.php'),
        ], 'config');

        /*
         * Migrations
         */
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        /*
         * Migrations
         */
        $this->publishes([
            __DIR__.'/../database/seeds/' => database_path('seeds'),
        ], 'seeds');

        /*
         * Translations
         */
        $this->publishes([
            __DIR__.'/../translations/' => resource_path('lang/'),
        ]);
        // use the vendor configuration file as fallback
        // $this->mergeConfigFrom(
        //     __DIR__.'/config/config.php', 'CRUD'
        // );

        $this->registerCommands();
    }

    /**
     * Define the routes for the application.
     *
     * @param \Illuminate\Routing\Router $router
     */
    public function setupRoutes(Router $router)
    {
        $crudFolder = config('filesystems.disks.crud.root');
        $path = str_replace(app_path().'/', '', $crudFolder);
        $namespace = $this->getAppNamespace().$path;

        $router->group([
            'namespace' => $namespace, 'middleware' => 'web',
        ], function ($router) {
            //Autoload route files
            $files = Storage::disk('crud')->allFiles('Routes2Include');
            foreach ($files as $file) {
                if (str_contains($file, 'Routes')) {
                    $fileSt = app_path($file);
                    $crudFolder = config('filesystems.disks.crud.root');
                    $path = str_replace(app_path().'/', '', $crudFolder);
                    require app_path($path.DIRECTORY_SEPARATOR.$file);
                }
            }
        });
    }

    /**
     * Register any package services.
     */
    public function register()
    {
        $this->registerCRUD();

        // Register dependency packages
        $this->app->register('Infinety\FileManager\FileManagerServiceProvider');
        $this->app->register('Collective\Html\HtmlServiceProvider');
        $this->app->register('Yajra\Datatables\DatatablesServiceProvider');
        $this->app->register('Jleon\LaravelPnotify\NotifyServiceProvider');
        $this->app->register('Jenssegers\Date\DateServiceProvider');
        $this->app->register('Spatie\MediaLibrary\MediaLibraryServiceProvider');

        // Register dependancy aliases
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('Html', 'Collective\Html\HtmlFacade');
        $loader->alias('Form', 'Collective\Html\FormFacade');
        $loader->alias('Notify', 'Jleon\LaravelPnotify\Notify');
        $loader->alias('Date', 'Jenssegers\Date\Date::class');
    }

    private function registerCRUD()
    {
        $this->app->bind('CRUD', function ($app) {
            return new CRUD($app);
        });
    }

    /**
     * @param $name
     * @param $controller
     * @param array $options
     */
    public static function resource($name, $controller, array $options = [])
    {
        // CRUD routes
        $prefixName = str_replace('/', '.', $name);
        Route::get($name.'/reorder', ['as' => $prefixName.'.reorder', 'uses' => $controller.'@reorder']);
        Route::get($name.'/reorder/{lang}', ['as' => $prefixName.'.reorder.item', 'uses' => $controller.'@reorder']);
        Route::post($name.'/reorder', ['as' => $prefixName.'.reorder.post', 'uses' => $controller.'@reorder']);
        Route::post($name.'/reorder/{lang}', ['as' => $prefixName.'.reorder.item.post', 'uses' => $controller.'@reorder']);
        Route::get($name.'/{id}/details', ['as' => $prefixName.'.details', 'uses' => $controller.'@showDetailsRow']);
        Route::get($name.'/{id}/translate/{lang}', ['as' => $prefixName.'.translate', 'uses' => $controller.'@translateItem']);
        Route::get($name.'/getData', ['as' => $prefixName.'.ajax', 'uses' => $controller.'@getData']);
        Route::resource($name, $controller, $options);
    }

    /**
     * Register datatables commands.
     */
    private function registerCommands()
    {
        $this->commands(CrudCreatorHelper::class);
        $this->commands(CrudCreatorHelperInline::class);
        $this->commands(CrudInstaller::class);
    }
}
