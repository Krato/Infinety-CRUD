<?php

namespace Infinety\CRUD\Commands;

use Illuminate\Console\Command;

use Storage;

use Carbon\Carbon;

class CrudCreatorHelperInline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'infinety-crud:createInline
                            {--singular=element : crud object name, singular}
                            {--plural=elements : crud object name, plural}
                            {--translatable=false : translatable crud(true,false)}
                            {--migrate=false : migrate now crud(true,false)}
                            {--fields=false : crud fields, array of fields}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '
    Inline Infinety-Crud Structure Creator Helper.
    
    Example = artisan infinety-crud:createInline    --singular=book 
                                                    --plural=books 
                                                    --translatable=true 
                                                    --migrate=false 
                                                    --fields="isbn|text|false","title|text|true","description|textarea|true","stars|number|false"
    
    ';

    protected $crud;
    protected $fields_array;
    protected $fields_array_temp;
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
        /*
        $this->info('--------------------------------');
        $this->info(' Infinety Crud Helper');
        $this->info(' Wellcome');
        $this->info('--------------------------------');
        */
        // Singular crud element
        $this->crud['singular'] = $this->option('singular');
        //$this->crud['singular'] = 'elefante';
        $this->crud['Singular'] = ucfirst($this->crud['singular']);
        
        // Plural crud element
        $this->crud['plural'] = $this->option('plural');
        //$this->crud['plural'] = 'elefantes';
        $this->crud['Plural'] = ucfirst($this->crud['plural']);
        
        // Translatable
        $this->crud['translatable'] = $this->option('translatable');

        /**
         * Fields for model
         */
        

        if ( $this->option('fields') ) {

            $newField = true;
            $fieldNumber = 0;



            $this->fields_array_temp = explode(',', $this->option('fields'));
            
            
            foreach ($this->fields_array_temp as $field ) {
                
                $field_parts = explode("|", $field );

                $this->fields_array[$fieldNumber]['name'] = $field_parts[0];
                $this->fields_array[$fieldNumber]['type'] = $field_parts[1];

                if ( $this->crud['translatable'] == 'true' ) {
                    
                    $this->fields_array[$fieldNumber]['translatable'] = $field_parts[2];
                    
                }
                $fieldNumber++;
            
            }

            $this->generateColumnsAndFields();

            $this->generateTableFields();

            $this->generateModelFields();
            
            
        }
        /*
        dump($this->crud);
        dump($this->fields_array_temp);
        dump($this->fields_array);
        */

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
        
        if ($this->option('migrate') == 'true') {
            
            $exitCode = $this->call('migrate');

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

        //dump($fieldsToFill);

        $this->crud['model_fillable'] = (string)view('crud::crud.model_fillable', compact( 'fieldsToFill' ))->render();
        //$this->crud['model_fillable'] = str_replace(" ","",$this->crud['model_fillable']);

        $this->crud['model_translatable_fillable'] = (string)view('crud::crud.model_translatable_fillable', compact( 'fieldsToFill'))->render();
        //$this->crud['model_translatable_fillable'] = str_replace(" ","",$this->crud['model_translatable_fillable']);
        
        //dump($this->crud);
        //dd();
    }
}