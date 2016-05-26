<?php

namespace Infinety\CRUD;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Route;
use Storage;
use Infinety\CRUD\Commands\CrudCreatorHelper;
use Infinety\CRUD\Commands\CrudCreatorHelperInline;

class CrudServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // use this if your package has views

        $this->loadViewsFrom(realpath(__DIR__.'/resources/views'), 'crud');

        // use this if your package has routes
        $this->setupRoutes($this->app->router);

        /**
         * Config File
         */
        $this->publishes([
             __DIR__.'/../config/infinety-crud.php' => config_path('infinety-crud.php'),
        ], 'config');

        /**
         * Migrations
         */
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations')
        ], 'migrations');

        /**
         * Migrations
         */
        $this->publishes([
            __DIR__.'/../database/seeds/' => database_path('seeds')
        ], 'seeds');

        /**
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
     *
     * @return void
     */
    public function setupRoutes(Router $router)
    {
        $router->group(['namespace' => 'Infinety\CRUD\Http\Controllers'], function ($router) {
            //Autoload route files
            $files = Storage::disk('crud')->allFiles('Routes2Include');
            foreach($files as $file){
                if(str_contains($file, 'Routes')){
                    $fileSt = app_path($file);
                    $crudFolder = config('filesystems.disks.crud.root');
                    $path = str_replace(app_path()."/", '', $crudFolder);
                    require app_path($path.DIRECTORY_SEPARATOR.$file);
                }
            }

        });
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCRUD();

        // Register dependency packages
        $this->app->register('Infinety\FileManager\FileManagerServiceProvider');
        $this->app->register('Collective\Html\HtmlServiceProvider');
        $this->app->register('Yajra\Datatables\DatatablesServiceProvider');
        $this->app->register('Jleon\LaravelPnotify\NotifyServiceProvider');
        
        // Register dependancy aliases
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('Html', 'Collective\Html\HtmlFacade');
        $loader->alias('Form', 'Collective\Html\FormFacade');
        $loader->alias('Notify', 'Jleon\LaravelPnotify\Notify');
    }

    private function registerCRUD()
    {
        $this->app->bind('CRUD', function ($app) {
            return new CRUD($app);
        });
    }

    public static function resource($name, $controller, array $options = [])
    {
        // CRUD routes

        Route::get($name.'/reorder', $controller.'@reorder');
        Route::get($name.'/reorder/{lang}', $controller.'@reorder');
        Route::post($name.'/reorder', $controller.'@saveReorder');
        Route::post($name.'/reorder/{lang}', $controller.'@saveReorder');
        Route::get($name.'/{id}/details', $controller.'@showDetailsRow');
        Route::get($name.'/{id}/translate/{lang}', $controller.'@translateItem');
        Route::get($name.'/getData', $controller.'@getData');

        Route::resource($name, $controller, $options);
    }

    /**
     * Register datatables commands.
     */
    private function registerCommands()
    {
        $this->commands(CrudCreatorHelper::class);
        $this->commands(CrudCreatorHelperInline::class);
    }
}
