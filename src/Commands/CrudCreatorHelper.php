<?php
namespace Infinety\CRUD\Commands;

use Illuminate\Console\Command;

use Storage;

use Carbon\Carbon;

class CrudCreatorHelper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'infinety-crud:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Infinety-Crud Structure Creator Helper';

    protected $crud;
    protected $fields_array;
    /*
    protected $columns;
    protected $fields;
    */


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {   
        /**
         * Define Data Container
         */
        $this->crud = array();

        /**
         * Get StoragePath
         */
        $this->getStoragePath();

        /**
         * Get DashBoard Path
         */
        $this->getDashBoardPath();

        /**
         * Collect Data
         */
        $this->collectData();

        /**
         * Make Controller
         */
        $this->makeController();
        
                
        /**
         * Make Model
         */
        $this->makeModel();

        /**
         * Make Model Translation
         */
        $this->makeModelTranslation();

        /**
         * Make Migration
         */
        $this->makeMigration();
             
    }

    /**
     * GetStoragePath
     */
    private function getStoragePath()
    {
        $path = config('filesystems.disks.crud.root');
        $this->crud['storagePath'] = str_replace(base_path()."/", '',  $path);
        $this->crud['storagePath'] = str_replace("/", '\\',  $this->crud['storagePath']);
        $this->crud['storagePath'] = ucfirst($this->crud['storagePath']).'\\';
        //dump($this->crud);
        //dd();
    }

    /**
     * getDashBoardPath
     */
    private function getDashBoardPath()
    {
        $path = config('infinety-crud.crud-route-prefix');
        $this->crud['dashBoardPath'] = $path;
        //dump($this->crud);
        //dd();
    }

    /**
     * Collect Data Funnction
     */
    private function collectData()
    {   
        $this->info('--------------------------------');
        $this->info(' Infinety Crud Helper');
        $this->info(' Wellcome');
        $this->info('--------------------------------');
        // Singular crud element
        $this->crud['singular'] = $this->ask('What is the crud object name, singular?');
        //$this->crud['singular'] = 'elefante';
        $this->crud['Singular'] = ucfirst($this->crud['singular']);
        
        // Plural crud element
        $this->crud['plural'] = $this->ask('What is the crud object name, plural?');
        //$this->crud['plural'] = 'elefantes';
        $this->crud['Plural'] = ucfirst($this->crud['plural']);
        
        // Translatable
        $this->crud['translatable'] = false;

        if ($this->confirm('Should the crud be translatable?')) {

            $this->crud['translatable'] = 'true';

        }
               
        /**
         * Fields for model
         */

        if ($this->confirm('Define Models fields?')) {

            $newField = true;
            $fieldNumber = 0;

            $this->fields_array = array();
            
            while ($newField) {
                $this->fields_array[$fieldNumber]['name'] = $this->ask('Field Name');

                $this->fields_array[$fieldNumber]['type'] = $this->anticipate('Field Type (checkbox, colorpicker, datetime_picker, email, enum, hidden, image, number,page_or_link, password, radio, redactor, select2, select, select_from_array, textarea, text, upload, url):', ['checkbox','colorpicker','datetime_picker','email','enum','hidden','image','number','page_or_link','password','radio','redactor','select2','select','select_from_array','textarea','text','upload','url']);

                if ( $this->crud['translatable'] == 'true' ) {
                    
                    if ( $this->confirm('Translatable Field?') ) {

                        $this->fields_array[$fieldNumber]['translatable'] = 'true';
                    }

                }
                 
                $fieldNumber++;

                $newField = $this->confirm('Add Another Field?');

            }

            $this->generateColumnsAndFields();

            $this->generateTableFields();

            $this->generateModelFields();
            
        }

    }

    /**
     * Make Controller function
     */
    private function makeController()
    {
        // Get Controller 
        $controller = $this->getTemplate('controller');
        // Replace strings in Controller
        $controller = $this->replaceStrings($controller);
        // Store Controller
        Storage::disk('crud')->put($this->crud['Singular'].'/Controllers/'.$this->crud['Singular'].'CrudController.php', $controller);
    }

    /**
     * Make Model
     */
    private function makeModel()
    {
        // Get Model 
        $model = $this->getTemplate('model');
        // Replace strings in Model
        $model = $this->replaceStrings($model);
        // Store Controller
        Storage::disk('crud')->put($this->crud['Singular'].'/Models/'.$this->crud['Singular'].'.php', $model);
    }

    /**
     * Make Model Translation
     */
    private function makeModelTranslation()
    {
        // Get Model Translation
        $model_translation = $this->getTemplate('translation');
        // Replace strings in Model Translation
        $model_translation = $this->replaceStrings($model_translation);
        // Store Model Translation
        Storage::disk('crud')->put($this->crud['Singular'].'/Models/'.$this->crud['Singular'].'Translation.php', $model_translation);
    }

    /**
     * Make Migration
     */
    private function makeMigration()
    {
        // Get Migration 
        $migration = $this->getTemplate('migration');
        // Replace strings in Migration
        $migration = $this->replaceStrings($migration);
        // Store Migration
        $migration_name = date('Y_m_d_His').'_create_'.$this->crud['singular'].'_table.php';
        // Creation of Migration File
        Storage::put('/CrudMigrations/'.$migration_name , $migration);
        // Move Migration File to migraons Folder        
        rename( storage_path('app').'/CrudMigrations/'.$migration_name , database_path('migrations').'/'.$migration_name );

        // Asking to process migrations.
        if ($this->confirm('Do you wish to migrate recent created crud migration? [y|N]')) {
            
            $exitCode = $this->call('migrate');
            dump($exitCode);
            dd();


        }
        
    }

    /**
     * Get Template file
     */
    private function getTemplate($name){
        
        $path = base_path('vendor/infinety-es/crud/src/resources/views/crud/'.$name.".blade.php");

        if(file_exists($path)){
            return file_get_contents($path);
        } else {
            return false;
        }

    }

    /**
     * Replace string in template files
     */
    private function replaceStrings($file){
        foreach($this->crud as $key => $value){
            $file = str_replace('__'.$key.'__', $value, $file);
        }
        return $file;

    }

    /**
     * GenerateColumnsAndFields
     */
    private function generateColumnsAndFields()
    {
        $fieldsToFill = $this->fields_array;
        $this->crud['columns'] = (string)view('crud::crud.columns', compact( 'fieldsToFill' ))->render();
        $this->crud['fields'] = (string)view('crud::crud.fields', compact( 'fieldsToFill' ))->render();
        

    }
    /**
     * GenerateTableFields
     */
    private function generateTableFields()
    {

        $field_equivalence = [
            'checkbox'          => 'boolean',
            'colorpicker'       => 'string',
            'datetime_picker'   => 'dateTime',
            'email'             => 'string',
            'enum'              => 'string',
            'hidden'            => 'string',
            'image'             => 'string',
            'number'            => 'integer',
            'page_or_link'      => 'string',
            'password'          => 'string',
            'radio'             => 'boolean',
            'redactor'          => 'text',
            'select2'           => 'string',
            'select'            => 'string',
            'select_from_array' => 'string',
            'textarea'          => 'text',
            'text'              => 'text',
            'upload'            => 'string',
            'url'               => 'string',
        ];

        $fieldsToFill = $this->fields_array;
        $this->crud['migration_fields'] = (string)view('crud::crud.migration_fields', compact( 'fieldsToFill','field_equivalence' ))->render();
        $this->crud['migration_translatable_fields'] = (string)view('crud::crud.migration_translatable_fields', compact( 'fieldsToFill','field_equivalence' ))->render();
        

    }

    /**
     * GenerateTableFields
     */
    private function generateModelFields()
    {

        $fieldsToFill = $this->fields_array;

        $this->crud['model_fillable'] = (string)view('crud::crud.model_fillable', compact( 'fieldsToFill' ))->render();
        //$this->crud['model_fillable'] = str_replace(" ","",$this->crud['model_fillable']);

        $this->crud['model_translatable_fillable'] = (string)view('crud::crud.model_translatable_fillable', compact( 'fieldsToFill'))->render();
        //$this->crud['model_translatable_fillable'] = str_replace(" ","",$this->crud['model_translatable_fillable']);
        
        //dump($this->crud);
        //dd();
    }
}