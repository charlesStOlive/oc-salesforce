<?php namespace Waka\SalesForce;

use App;
use Backend;
use Carbon\Carbon;
use Config;
use Event;
use Illuminate\Foundation\AliasLoader;
use Lang;
use System\Classes\PluginBase;
use Waka\Wconfig\Models\Settings;

/**
 * SalesForce Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * @var array Plugin dependencies
     */
    public $require = [
        'Waka.Utils',
        'Wcli.Wconfig',
    ];
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name' => 'SalesForce',
            'description' => 'Branchement salesforce nécessite Wcli.Wconfig pour fonctionner',
            'author' => 'Waka',
            'icon' => 'icon-leaf',
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {

    }

    public function registerSchedule($schedule)
    {
        //trace_log(Carbon::parse(Settings::get('sf_cron_time'))->format('H:i'));
        //Lancement des cron
        $schedule->call(function () {
            //trace_log("Je lance le cron SF");
            $usersIds = Settings::get('sf_responsable');
            //trace_log($usersIds);
            $forrest = false;
            try {

                \Forrest::refresh();;
                $forrest = true;
                //trace_log("Je tente une connection");
            } catch (\Exception $e) {
                //trace_log("Erreur de connection SF");
                //trace_log($e);
                foreach ($usersIds as $userId) {
                    $user = \Backend\Models\User::find($userId);
                    if ($user) {
                        $datasEmail = [
                            'emails' => $user->email,
                            'subject' => "Erreur authentification SalesForce",
                        ];
                        $mail = new \Waka\Mailer\Classes\MailCreator('waka.salesforce::error_auth_sf', 'slug');
                        $mail->renderMail($user->id, $datasEmail);
                    }
                }
            }
            if ($forrest) {
                //trace_log("OK je lance l'import");
                //Lancement du CRON
                $sf = new \Waka\SalesForce\Classes\SalesForceConfig();
                $sf->execImports();

                //trace_log("Import terminé");
                //A la fin j'envoie le mail de bilan aux collaborateurs
                foreach ($usersIds as $userId) {
                    $user = \Backend\Models\User::find($userId);
                    if ($user) {
                        $datasEmail = [
                            'emails' => $user->email,
                            'subject' => "Bilan SalesForce",
                        ];
                        $mail = new \Waka\Mailer\Classes\MailCreator('waka.salesforce::siege.sf', 'slug');
                        $mail->renderMail($user->id, $datasEmail);
                    }
                }

            }
            //})->everyMinute();
        })->dailyAt(Carbon::parse(Settings::get('sf_cron_time'))->format('H:i'));

    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        $this->bootPackages();

        Event::listen('backend.form.extendFields', function ($widget) {

            //trace_log('yo');

            // Only for the User controller

            if (!$widget->getController() instanceof \System\Controllers\Settings) {
                return;
            }

            // Only for the User model
            if (!$widget->model instanceof Settings) {
                return;
            }

            if ($widget->isNested === true) {
                return;
            }

            $widget->addTabFields([
                'sf_responsable' => [
                    'tab' => 'Sales Force',
                    'label' => "Collaborateurs recevant l'email de bilan Sales Force",
                    'type' => 'taglist',
                    'mode' => 'array',
                    'useKey' => 'true',
                    'options' => 'listUsers',
                ],
                'sf_active_imports' => [
                    'tab' => 'Sales Force',
                    'label' => 'waka.salesforce::lang.settings.active_imports',
                    'type' => 'checkboxlist',
                    'quickselect' => true,
                    'options' => 'listImports',
                ],

                'sf_oldest_date' => [
                    'tab' => 'Sales Force',
                    'label' => 'waka.salesforce::lang.settings.oldest_date',
                    'type' => 'datepicker',
                ],
                'sf_cron_time' => [
                    'tab' => 'Sales Force',
                    'label' => "Heure d'execution du CRON",
                    'type' => 'datepicker',
                    'mode' => 'time',
                    'span' => 'left',
                    'width' => '100px',
                ],
            ]);
        });

    }

    public function bootPackages()
    {
        // Get the namespace of the current plugin to use in accessing the Config of the plugin
        $pluginNamespace = str_replace('\\', '.', strtolower(__NAMESPACE__));

        // Instantiate the AliasLoader for any aliases that will be loaded
        $aliasLoader = AliasLoader::getInstance();

        // Get the packages to boot
        $packages = Config::get($pluginNamespace . '::packages');

        // Boot each package
        foreach ($packages as $name => $options) {
            // Setup the configuration for the package, pulling from this plugin's config
            if (!empty($options['config']) && !empty($options['config_namespace'])) {
                Config::set($options['config_namespace'], $options['config']);
            }
            // Register any Service Providers for the package
            if (!empty($options['providers'])) {
                foreach ($options['providers'] as $provider) {
                    App::register($provider);
                }
            }
            // Register any Aliases for the package
            if (!empty($options['aliases'])) {
                foreach ($options['aliases'] as $alias => $path) {
                    $aliasLoader->alias($alias, $path);
                }
            }
        }
    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        return []; // Remove this line to activate

        return [
            'Waka\SalesForce\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'waka.salesforce.admin' => [
                'tab' => 'Waka -SalesForce',
                'label' => 'Administrateur SalesForce',
            ],
        ];
    }

    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        return []; // Remove this line to activate

        return [
            'salesforce' => [
                'label' => 'SalesForce',
                'url' => Backend::url('waka/salesforce/mycontroller'),
                'icon' => 'icon-leaf',
                'permissions' => ['waka.salesforce.*'],
                'order' => 500,
            ],
        ];
    }
    public function registerSettings()
    {

        return [
            // 'sales_force' => [
            //     'label' => Lang::get('waka.salesforce::lang.menu.settings'),
            //     'description' => Lang::get('waka.salesforce::lang.menu.settings_description'),
            //     'category' => Lang::get('waka.salesforce::lang.menu.category'),
            //     'icon' => 'icon-cog',
            //     'class' => 'Waka\SalesForce\Models\Settings',
            //     'order' => 101,
            //     'permissions' => ['waka.salesforce.admin', 'waka.salesforce.admin'],
            // ],
            'logsfs' => [
                'label' => Lang::get('waka.salesforce::lang.menu.logsf'),
                'description' => Lang::get('waka.salesforce::lang.menu.logsf_description'),
                'category' => Lang::get('waka.salesforce::lang.menu.category'),
                'icon' => 'icon-skyatlas',
                'url' => Backend::url('waka/salesforce/logsfs'),
                'order' => 130,
                'permissions' => ['waka.salesforce.admin'],
            ],
        ];
    }
}
