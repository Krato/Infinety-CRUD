<?php

namespace Infinety\CRUD\Http\Controllers;

use Datatables;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Infinety\CRUD\Http\Requests\CrudRequest as StoreRequest;
use Infinety\CRUD\Http\Requests\CrudRequest as UpdateRequest;
use Jenssegers\Date\Date;
use Notify;
//use Infinety\CRUD\Models\Locale;
use Storage;

class CrudController extends BaseController
{
    use DispatchesJobs, ValidatesRequests;

    /**
     * @var array
     */
    public $data = [];
    /**
     * @var array
     */
    public $crud = [
        'model'                 => "\App\Models\Entity",
        'entity_name'           => 'entry',
        'entity_name_plural'    => 'entries',
        'ajax_load'             => false,
        'view_table_permission' => true,
        'add_permission'        => true,
        'edit_permission'       => true,
        'delete_permission'     => true,
        'reorder_permission'    => true,
        'reorder_max_level'     => 3,
        'details_row'           => false,
        'is_translate'          => false,
        'locale_id'             => 'iso',
        'locale_column'         => 'locale',
    ];

    /**
     * @var mixed
     */
    protected $locale;

    public function __construct()
    {
        $this->locale = config('infinety-crud.locale-model');

        // If is multilanguage fill languages array with languages availables
        if (isset($this->crud['is_translate']) && $this->crud['is_translate'] == true) {
            $locales = new $this->locale();
            $this->crud['languages'] = $locales->getAvailables()->toArray();
        }

        if (!isset($this->crud['locale_id'])) {
            $this->crud['locale_id'] = 'iso';
            $this->crud['locale_column'] = 'locale';
        }

        $this->data['crud'] = $this->crud;
    }

    /**
     * Display all rows in the database for this entity.
     *
     * @return Response
     */
    public function index()
    {

        // SECURITY:
        // if view_table_permission is false, abort
        if (isset($this->crud['view_table_permission']) && !$this->crud['view_table_permission']) {
            abort(403, 'Not allowed.');
        }

        // get all results for that entity
        $model = $this->crud['model'];

        if (isset($this->crud['is_translate']) && $this->crud['is_translate'] == true) {
            $this->data['entries'] = $model::orderby('id', 'ASC')->get();
        } else {
            $this->data['entries'] = $model::all();
        }

        // add the fake fields for each entry
        //dd($this->data['entries']);
        //foreach ($this->data['entries'] as $key => $entry) {
        //    $entry->addFakes($this->getFakeColumnsAsArray());
        //}

        $this->prepareColumns();
        $this->data['crud'] = $this->crud;

        // load the view from /resources/views/vendor/dick/crud/ if it exists, otherwise load the one in the package

        return $this->firstViewThatExists('vendor.infinety.crud.list', 'crud::list', $this->data);
    }

    /**
     * Show the form for creating inserting a new row.
     *
     * @return Response
     */
    public function create()
    {
        // SECURITY:
        // if add_permission is false, abort
        if (isset($this->crud['add_permission']) && !$this->crud['add_permission']) {
            abort(403, 'Not allowed.');
        }

        // get the fields you need to show

        if (isset($this->data['crud']['create_fields'])) {
            $this->crud['fields'] = $this->data['crud']['create_fields'];
        }

        // prepare the fields you need to show
        $this->prepareFields();
        $this->data['crud'] = $this->crud;

        // load the view from /resources/views/vendor/dick/crud/ if it exists, otherwise load the one in the package

        return $this->firstViewThatExists('vendor.infinety.crud.create', 'crud::create', $this->data);
    }

    /**
     * Return model in Datatable ajax | Also search by ajax.
     *
     * @return mixed
     */
    public function getData()
    {
        $model = $this->crud['model'];

        $columns = $this->crud['columns'];

        //Check if has a pivot column
        $pivots = array_pluck($this->crud['columns'], ['pivot']);
        $isPivot = array_search('true', $pivots);

        if ($isPivot != false) {
            $pivotField = $this->crud['columns'][$isPivot];
//            dd($pivotField);
            $data = $model::select('*')->get();
            //dd($columns);
        } else {
            $data = $model::select('*')->get();
        }

        $datatable = Datatables::of($data);

        foreach ($columns as $column) {
            if ((isset($column['type']) && $column['type'] == 'select_multiple') || (isset($column['type']) && $column['type'] == 'select2_multiple')) {
                $datatable
                    ->addColumn($column['name'], '')
                    ->editColumn($column['name'], function ($columnInfo) use ($column) {
                        $results = $columnInfo->{$column['entity']}()->getResults();
                        $html = '-';
                        if ($results && $results->count()) {
                            $results_array = $results->pluck($column['attribute'], 'id');
                            $html = implode(', ', $results_array->toArray());
                        }

                        return $html;
                    });
            } elseif ((isset($column['type']) && $column['type'] == 'select')) {
                $datatable
                    ->addColumn($column['name'], '')
                    ->editColumn($column['name'], function ($columnInfo) use ($column) {
                        $html = '-';
                        if ($columnInfo->{$column['entity']}()->getResults()) {
                            $html = $columnInfo->{$column['entity']}()->getResults()->{$column['attribute']};
                        }

                        return $html;
                    });
            } elseif (isset($column['type']) && $column['type'] == 'model_function') {
                $datatable
                    ->addColumn($column['name'], '')
                    ->editColumn($column['name'], function ($columnInfo) use ($column) {
                        return $columnInfo->{$column['function_name']}();
                    });
            } elseif (isset($column['type']) && $column['type'] == 'image') {
                $datatable
                    ->addColumn($column['name'], '')
                    ->editColumn($column['name'], function ($columnInfo) use ($column) {
                        return "<img src='".asset($columnInfo->{$column['name']})."' width='50%' />";
                    });
            } elseif (isset($column['type']) && $column['type'] == 'browse') {
                $datatable
                    ->addColumn($column['name'], '')
                    ->editColumn($column['name'], function ($columnInfo) use ($column) {
                        if (isset($column['browse']) && $column['browse'] != 'image') {
                            return $columnInfo->content;
                        }

                        return "<img src='".asset($columnInfo->{$column['name']})."' width='50%' />";
                    });
            } elseif (isset($column['type']) && isset($column['pivot']) && $column['pivot'] == true) {
                $datatable
                    ->editColumn($column['name'], function ($columnInfo) use ($column) {
                        //                      dd($columnInfo);
                        //                      dd($column); //["model"]
                        $pivotModel = $this->crud['model'];
                        $dataPivot = $column['model']::find($columnInfo[$column['entity']]);
                        if ($dataPivot) {
                            return $dataPivot->$column['attribute'];
                        }
                    });
            } elseif (isset($column['type']) && $column['type'] == 'date') {
                $datatable
                    ->addColumn($column['name'], '')
                    ->editColumn($column['name'], function ($columnInfo) use ($column) {
                        if ($columnInfo->{$column['name']} != null) {
                            $locale = (isset($column['language'])) ? $column['language'] : 'en';
                            $format = (isset($column['format'])) ? $column['format'] : 'Y-m-d';
                            Date::setLocale($locale);
                            $date = Date::parse($columnInfo->{$column['name']});

                            return $date->format($format);
                        }

                        return '';
                    });
            } else {
                if (array_search('content', $columns)) {
                    $datatable
                        ->editColumn($column['name'], function ($columnInfo) use ($column) {
                            return strip_tags(Str::words($columnInfo->content, 50, '...'));
                        });
                } else {
                    $datatable
                        ->editColumn($column['name'], function ($columnInfo) use ($column) {
                            if (trim($columnInfo->{$column['name']}) == '') {
                                if (isset($this->crud['is_translate']) && $this->crud['is_translate'] == true) {
                                    if ($columnInfo->translate()) {
                                        $columnInfo->{$column['name']} = $columnInfo->{$column['name']};
                                    }
                                }
                            }

                            return str_limit(strip_tags($columnInfo->{$column['name']}), 80, '[...]');
                        });
                }
            }
        }

        if (!(isset($crud['edit_permission']) && $crud['edit_permission'] === false && isset($crud['delete_permission']) && $crud['delete_permission'] === false)) {
            $datatable
                ->addColumn('actions', '')
                ->editColumn('actions', function ($column) {
                    $html = '';
                    if (!(isset($crud['edit_permission']) && !$crud['edit_permission'])) {
                        $html .= '<a href="'.url($this->crud['route']).'/'.$column->id.'/edit" class="btn btn-xs btn-complete "><i class="fa fa-edit p-r-10"></i>'._(trans('crud.edit')).'</a>';
                    }
                    if (!(isset($crud['delete_permission']) && !$crud['delete_permission'])) {
                        $html .= '<a href="'.url($this->crud['route']).'/'.$column->id.'" class="btn btn-xs btn-danger m-l-5" data-button-type="delete"><i class="fa fa-trash p-r-10 "></i>'._(trans('crud.delete')).'</a>';
                    }

                    return $html;
                });
        }

        $datatable->rawColumns(['actions']);

        return $datatable->make(true);
    }

    /**
     * Store a newly created resource in the database.
     *
     * @return Response
     */
    public function storeCrud(StoreRequest $request = null)
    {

        // SECURITY:
        // if add_permission is false, abort
        if (isset($this->crud['add_permission']) && !$this->crud['add_permission']) {
            abort(403, 'Not allowed.');
        }
        // compress the fake fields into one field
        $model = $this->crud['model'];

        $values_to_store = $this->compactFakeFields(\Request::all());

        $values_to_store = $this->hasFilesToUpload($values_to_store);
        $translated_items = false;
        if (isset($values_to_store['translate'])) {
            if (isset($this->data['crud']['is_translate']) && $this->data['crud']['is_translate'] == true) {
                $translated_items = $values_to_store['translate'];

                foreach ($translated_items as $k => $langItems) {
                    if (isset($langItems['extras'])) {
                        $translated_items[$k]['extras_trans'] = $langItems['extras'];
                        unset($translated_items[$k]['extras']);
                    }
                }

                unset($values_to_store['translate']);
            }
        }

        $item = $model::create($values_to_store);
        $fields = $this->getFields();

        if (isset($fields['normal'])) {
            if ($this->hasMedia($fields['normal'])) {
                $this->processMedia($fields['normal'], \Request::all(), $item);
            }
        } else {
            if ($this->hasMedia($fields)) {
                $this->processMedia($fields, \Request::all(), $item);
            }
        }

        if ($translated_items) {
            $item->translations()->delete();

            $modelTranslatable = $this->crud['model_translate'];
            $models = [];
            $valuesTranslated = [];
            foreach ($this->data['crud']['languages'] as $language) {
                $table = new $model();
                $table = $table->getTable();

                $itemInfo = [$table.'_id' => $item->id, $this->crud['locale_column'] => $language[$this->crud['locale_id']]];

                $translatedFields = array_merge($itemInfo, $translated_items[$language[$this->crud['locale_id']]]);

                $valuesTranslated[] = $translatedFields;
                $models[] = $modelTranslatable::create($translatedFields);
            }

            $count = 0;
            foreach ($this->data['crud']['languages'] as $language) {
                $fields = $this->getFields();
                $values = \Request::get('translate');
                $valuesTrans = $values[$language[$this->crud['locale_id']]];
                if ($this->hasMedia($fields['translate'][$language[$this->crud['locale_id']]])) {
                    $this->processMedia($fields['translate'][$language[$this->crud['locale_id']]], $valuesTrans, $models[$count]);
                }
                ++$count;
            }
        }

        // if it's a relationship with a pivot table, also sync that
        $this->prepareFields();
        foreach ($this->crud['fields'] as $k => $field) {
            if (isset($field['pivot']) && $field['pivot'] == true) {
                //&& \Request::input($field['name'] != 0)
                $model::find($item->id)->{$field['entity']}()->attach(\Request::input($field['name']));
            }
        }

        // show a success message
        Notify::success(trans('crud.insert_success'), 'Success Created');

        // redirect the user where he chose to be redirected
        switch (\Request::input('redirect_after_save')) {
            case 'current_item_edit':
                return Redirect::to($this->crud['route'].'/'.$item->id.'/edit');
                break;

            default:
                return Redirect::to(\Request::input('redirect_after_save'));
                break;
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        // SECURITY:
        // if edit_permission is false, abort
        if (isset($this->crud['edit_permission']) && !$this->crud['edit_permission']) {
            abort(403, 'Not allowed.');
        }

        // get the info for that entry
        $model = $this->crud['model'];
        $this->data['entry'] = $model::find($id);

        $this->data['entry']->addFakes($this->getFakeColumnsAsArray());

        if (isset($this->data['crud']['update_fields'])) {
            $this->crud['fields'] = $this->data['crud']['update_fields'];
        }

        // prepare the fields you need to show and prepopulate the values

        $this->prepareFields($this->data['entry']);

        $this->data['crud'] = $this->crud;

        // load the view from /resources/views/vendor/dick/crud/ if it exists, otherwise load the one in the package

        return $this->firstViewThatExists('vendor.infinety.crud.edit', 'crud::edit', $this->data);
    }

    /**
     * Update the specified resource in the database.
     *
     * @param int $id
     *
     * @return Response
     */
    public function updateCrud(UpdateRequest $request = null)
    {
        // if edit_permission is false, abort
        if (isset($this->crud['edit_permission']) && !$this->crud['edit_permission']) {
            abort(403, 'Not allowed.');
        }

        $model = $this->crud['model'];

        $this->prepareFields($model::find(\Request::input('id')));

        $values_to_store = $this->compactFakeFields(\Request::all());

        $values_to_store = $this->hasFilesToUpload($values_to_store);

        $translated_items = false;
        if (isset($this->data['crud']['is_translate']) && $this->data['crud']['is_translate'] == true && isset($values_to_store['translate'])) {
            $translated_items = $values_to_store['translate'];

            foreach ($translated_items as $k => $langItems) {
                if ($this->crud['locale_id'] != 'iso') {
                    $locale = Locale::whereIso($k)->first();
                    $translated_items[$locale->id] = $translated_items[$k];
                }
                if (isset($langItems['extras'])) {
                    $translated_items[$k]['extras_trans'] = $langItems['extras'];
                    unset($translated_items[$k]['extras']);
                }
            }

            unset($values_to_store['translate']);
        }

        $item = $model::find(\Request::input('id'))
            ->update($values_to_store);

        //Check if has Media option
        $fields = $this->getFields();
        $model = $model::find(\Request::input('id'));
        if (isset($fields['normal'])) {
            if ($this->hasMedia($fields['normal'])) {
                $this->processMedia($fields['normal'], \Request::all(), $model);
            }
        } else {
            if ($this->hasMedia($fields)) {
                $this->processMedia($fields, \Request::all(), $model);
            }
        }

        if ($translated_items) {
            $modelTranslatable = $this->crud['model_translate'];

            $models = [];
            $valuesTranslated = [];

            foreach ($this->data['crud']['languages'] as $language) {
                $table = new $model();
                $table = $table->getTable();
                $exists = $modelTranslatable::where($table.'_id', \Request::input('id'))
                    ->where($this->crud['locale_column'], $language[$this->crud['locale_id']])->first();

                if ($exists) {
                    $translatedFields = $translated_items[$language[$this->crud['locale_id']]];
                    $valuesTranslated[] = $translatedFields;

                    $modelTranslatable::where($table.'_id', \Request::input('id'))
                        ->where($this->crud['locale_column'], $language[$this->crud['locale_id']])
                        ->update($translated_items[$language[$this->crud['locale_id']]]);

                    $models[] = $exists;
                } else {
                    $itemInfo = [$table.'_id' => \Request::input('id'), $this->crud['locale_column'] => $language[$this->crud['locale_id']]];

                    $translatedFields = array_merge($itemInfo, $translated_items[$language[$this->crud['locale_id']]]);

                    $valuesTranslated[] = $translatedFields;

                    $models[] = $modelTranslatable::create($translatedFields);
                }
            }

            //Check if has Media option
            $count = 0;
            foreach ($this->data['crud']['languages'] as $language) {
                $fields = $this->getFields();
                $values = \Request::get('translate');
                $valuesTrans = $values[$language[$this->crud['locale_id']]];
                if ($this->hasMedia($fields['translate'][$language[$this->crud['locale_id']]])) {
                    $this->processMedia($fields['translate'][$language[$this->crud['locale_id']]], $valuesTrans, $models[$count]);
                }
                ++$count;
            }
        }

        // if it's a relationship with a pivot table, also sync that
        foreach ($this->crud['fields'] as $k => $field) {
            if (isset($field['pivot']) && $field['pivot'] == true) {
                $model::find(\Request::input('id'))->$field['entity']()->sync(\Request::input($field['name']));
            }
        }

        // show a success message
        Notify::success(trans('crud.update_success'), 'Success Updated');

        if (isset($this->crud['redirect_self']) && $this->crud['redirect_self'] == true) {
            return Redirect::to($this->crud['route'].'/'.\Request::input('id').'/edit');
        }

        return \Redirect::to($this->crud['route']);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return Response
     */
    public function show($id)
    {
        // get the info for that entry
        $model = $this->crud['model'];
        $this->data['entry'] = $model::find($id);
        $this->data['entry']->addFakes($this->getFakeColumnsAsArray());
        $this->data['crud'] = $this->crud;

        // load the view from /resources/views/vendor/dick/crud/ if it exists, otherwise load the one in the package

        return $this->firstViewThatExists('vendor.infinety.crud.show', 'crud::show', $this->data);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        // SECURITY:
        // if delete_permission is false, abort
        if (isset($this->crud['delete_permission']) && !$this->crud['delete_permission']) {
            //abort(403, trans('crud.unauthorized_access'));
        }

        $model = $this->crud['model'];
        $item = $model::find($id);
        $this->checkHasImagesToDelete($item);
        $item->delete();

        return 'true';
    }

    /**
     *  Reorder the items in the database using the Nested Set pattern.
     *
     *  Database columns needed: id, parent_id, lft, rgt, depth, name/title
     *
     *  @return Response
     */
    public function reorder($lang = false)
    {
        // if reorder_table_permission is false, abort
        //        if (isset($this->crud['reorder_permission']) && ! $this->crud['reorder_permission']) {
        //            abort(403, 'Not allowed.');
        //        }

        if ($lang == false) {
            $lang = \Lang::locale();
        }

        // get all results for that entity
        $model = $this->crud['model'];
        if (property_exists($model, 'translatable')) {
            $this->data['entries'] = $model::where('translation_lang', $lang)->get();
            $this->data['languages'] = \Dick\TranslationManager\Models\Language::all();
            $this->data['active_language'] = $lang;
        } else {
            $this->data['entries'] = $model::all();
        }
        $this->data['crud'] = $this->crud;

        // load the view from /resources/views/vendor/dick/crud/ if it exists, otherwise load the one in the package

        return $this->firstViewThatExists('vendor.infinety.crud.reorder', 'crud::reorder', $this->data);
    }

    /**
     * Save the new order, using the Nested Set pattern.
     *
     * Database columns needed: id, parent_id, lft, rgt, depth, name/title
     *
     * @return
     */
    public function saveReorder()
    {
        // if reorder_table_permission is false, abort
        //        if (isset($this->crud['reorder_permission']) && ! $this->crud['reorder_permission']) {
        //            abort(403, 'Not allowed.');
        //        }

        $model = $this->crud['model'];
        $count = 0;
        $all_entries = \Request::input('tree');

        if (count($all_entries)) {
            foreach ($all_entries as $key => $entry) {
                if ($entry['item_id'] != '' && $entry['item_id'] != null) {
                    $item = $model::find($entry['item_id']);
                    $item->parent_id = $entry['parent_id'];
                    $item->depth = $entry['depth'];
                    $item->lft = $entry['left'];
                    $item->rgt = $entry['right'];
                    $item->save();

                    ++$count;
                }
            }
        } else {
            return false;
        }

        return 'success for '.$count.' items';
    }

    /**
     * Used with AJAX in the list view (datatables) to show extra information about that row that didn't fit in the table.
     * It defaults to showing all connected translations and their CRUD buttons.
     *
     * It's enabled by:
     * - setting the $crud['details_row'] variable to true;
     * - adding the details route for the entity; ex: Route::get('page/{id}/details', 'PageCrudController@showDetailsRow');
     */
    public function showDetailsRow($id)
    {
        // get the info for that entry
        $model = $this->crud['model'];
        $this->data['entry'] = $model::find($id);
        $this->data['entry']->addFakes($this->getFakeColumnsAsArray());
        $this->data['original_entry'] = $this->data['entry'];
        $this->data['crud'] = $this->crud;

        if (property_exists($model, 'translatable')) {
            $this->data['translations'] = $this->data['entry']->translations();

            // create a list of languages the item is not translated in
            $this->data['languages'] = \Dick\TranslationManager\Models\Language::all();
            $this->data['languages_already_translated_in'] = $this->data['entry']->translationLanguages();
            $this->data['languages_to_translate_in'] = $this->data['languages']->diff($this->data['languages_already_translated_in']);
            $this->data['languages_to_translate_in'] = $this->data['languages_to_translate_in']->reject(function ($item) {
                return $item->abbr == \Lang::locale();
            });
        }

        // load the view from /resources/views/vendor/dick/crud/ if it exists, otherwise load the one in the package

        return $this->firstViewThatExists('vendor.infinety.crud.details_row', 'crud::details_row', $this->data);
    }

    /**
     * Duplicate an existing item into another language and open it for editing.
     */
    public function translateItem($id, $lang)
    {
        $model = $this->crud['model'];
        $this->data['entry'] = $model::find($id);
        // check if there isn't a translation already
        $existing_translation = $this->data['entry']->translation($lang);

        if ($existing_translation) {
            $new_entry = $existing_translation;
        } else {
            // get the info for that entry
            $new_entry_attributes = $this->data['entry']->getAttributes();
            $new_entry_attributes['translation_lang'] = $lang;
            $new_entry_attributes['translation_of'] = $id;
            $new_entry_attributes = array_except($new_entry_attributes, 'id');

            $new_entry = $model::create($new_entry_attributes);
        }

        // redirect to the edit form for that translation

        return redirect(str_replace($id, $new_entry->id, str_replace('translate/'.$lang, 'edit', \Request::url())));
    }

    /**
     * COMMODITY FUNCTIONS.
     */

    /**
     * Allow replace the default views by placing a view with the same name in
     * /resources/views/vendor/dick/crud/. If no such view exists, load the one from the package.
     *
     * @param view  $first_view  - the first view to try, ex: vendor.dick.crud.edit
     * @param view  $second_view - the second view to try, ex: crud::edit
     * @param array $information - the information to send to the view, usually $this->data
     *
     * @return HTTP Response
     */
    protected function firstViewThatExists($first_view, $second_view, $information)
    {

        // load the first view if it exists, otherwise load the second one
        if (view()->exists($first_view)) {
            return view($first_view, $information);
        } else {
            return view($second_view, $information);
        }
    }

    /**
     * Refactor the request array to something that can be passed to the model's create or update function.
     * The resulting array will only include the fields that are stored in the database and their values,
     * plus the '_token' and 'redirect_after_save' variables.
     *
     * @param Request $request - everything that was sent from the form, usually \Request::all()
     *
     * @return array
     */
    protected function compactFakeFields($request)
    {
        $this->prepareFields();

        $this->crud['fields'] = $this->getFields();

        if (isset($fields['normal'])) {
            $fields = $this->crud['fields'];
        }

        $fake_field_columns_to_encode = [];

        // go through each defined field

        if (isset($this->data['crud']['is_translate']) && $this->data['crud']['is_translate'] == true) {
            if (isset($this->crud['fields']['normal'])) {
                foreach ($this->crud['fields']['normal'] as $k => $field) {

                    // if it's a fake field
                    if (isset($field['fake']) && $field['fake'] == true) {
                        // add it to the request in its appropriate variable - the one defined, if defined

                        if (isset($field['store_in'])) {
                            if ($field['type'] == 'image' || $field['type'] == 'upload') {
                                $file = \Request::file($field['name']);
                                $request[$field['store_in']][$field['name']] = $file->getClientOriginalName();
                            } else {
                                $request[$field['store_in']][$field['name']] = $request[$field['name']];
                            }

                            $remove_fake_field = array_pull($request, $field['name']);

                            if (!in_array($field['store_in'], $fake_field_columns_to_encode, true)) {
                                array_push($fake_field_columns_to_encode, $field['store_in']);
                            }
                        } else {
                            //otherwise in the one defined in the $crud variable

                            if ($field['type'] == 'image' || $field['type'] == 'upload') {
                                $file = \Request::file($field['name']);
                                $request['extras'][$field['name']] = $file->getClientOriginalName();
                            } else {
                                $request['extras'][$field['name']] = $request[$field['name']];
                            }
                            $remove_fake_field = array_pull($request, $field['name']);

                            if (!in_array('extras', $fake_field_columns_to_encode, true)) {
                                array_push($fake_field_columns_to_encode, 'extras');
                            }
                        }
                    }
                }
                // Convert to json data fake columns
                if (count($fake_field_columns_to_encode)) {
                    foreach ($fake_field_columns_to_encode as $key => $value) {
                        $request[$value] = json_encode($request[$value]);
                    }
                }
            }
            if (isset($this->crud['fields']['translate'])) {

                // Loop for translated strings
                foreach ($this->data['crud']['languages'] as $language) {
                    foreach ($this->crud['fields']['translate'][$language['iso']] as $k => $field) {

                        // if it's a fake field
                        if (isset($field['fake']) && $field['fake'] == true) {

                            // add it to the request in its appropriate variable - the one defined, if defined
                            if (isset($field['store_in'])) {
                                $request['translate'][$language['iso']][$field['store_in']][$field['name']] = $request['translate'][$language['iso']][$field['name']];

                                $remove_fake_field = array_pull($request['translate'][$language['iso']], $field['name']);

                                if (!in_array($field['store_in'], $fake_field_columns_to_encode, true)) {
                                    array_push($fake_field_columns_to_encode, $field['store_in']);
                                }
                            } else {
                                //otherwise in the one defined in the $crud variable

                                $request['extras'][$field['name']] = $request[$field['name']];

                                $remove_fake_field = array_pull($request, $field['name']);
                                if (!in_array('extras', $fake_field_columns_to_encode, true)) {
                                    array_push($fake_field_columns_to_encode, 'extras');
                                }
                            }
                        }
                    }

                    if (count($fake_field_columns_to_encode)) {
                        foreach ($fake_field_columns_to_encode as $key => $value) {
                            if (isset($request['translate'][$language['iso']][$value])) {
                                $request['translate'][$language['iso']][$value] = json_encode($request['translate'][$language['iso']][$value]);
                            }
                        }
                        if (isset($request['translate'][$language['iso']][$value.'_trans']) && isset($request['translate'][$language['iso']][$value])) {
                            $request['translate'][$language['iso']][$value.'_trans'] = $request['translate'][$language['iso']][$value];
                            unset($request['translate'][$language['iso']][$value]);
                        }
                    }
                }
            }
        } else {
            foreach ($this->crud['fields'] as $k => $field) {

                // if it's a fake field
                if (isset($this->crud['fields'][$k]['fake']) && $this->crud['fields'][$k]['fake'] == true) {
                    // add it to the request in its appropriate variable - the one defined, if defined
                    if (isset($this->crud['fields'][$k]['store_in'])) {
                        if ($field['type'] == 'image' || $field['type'] == 'upload') {
                            $file = \Request::file($field['name']);
                            $request[$this->crud['fields'][$k]['store_in']][$this->crud['fields'][$k]['name']] = $file->getClientOriginalName();
                        } else {
                            $request[$this->crud['fields'][$k]['store_in']][$this->crud['fields'][$k]['name']] = $request[$this->crud['fields'][$k]['name']];
                        }

                        $remove_fake_field = array_pull($request, $this->crud['fields'][$k]['name']);

                        if (!in_array($this->crud['fields'][$k]['store_in'], $fake_field_columns_to_encode, true)) {
                            array_push($fake_field_columns_to_encode, $this->crud['fields'][$k]['store_in']);
                        }
                    } else {
                        //otherwise in the one defined in the $crud variable

                        if ($field['type'] == 'image' || $field['type'] == 'upload') {
                            $file = \Request::file($field['name']);
                            $request['extras'][$this->crud['fields'][$k]['name']] = $file->getClientOriginalName();
                        } else {
                            $request['extras'][$this->crud['fields'][$k]['name']] = $request[$this->crud['fields'][$k]['name']];
                        }

                        $remove_fake_field = array_pull($request, $this->crud['fields'][$k]['name']);
                        if (!in_array('extras', $fake_field_columns_to_encode, true)) {
                            array_push($fake_field_columns_to_encode, 'extras');
                        }
                    }
                }
            }
            if (count($fake_field_columns_to_encode)) {
                foreach ($fake_field_columns_to_encode as $key => $value) {
                    $request[$value] = json_encode($request[$value]);
                }
            }
        }

        // json_encode all fake_value columns in the database, so they can be properly stored and interpreted

        // if there are no fake fields defined, this will just return the original Request in full
        // since no modifications or additions have been made to $request

        return $request;
    }

    /**
     * Returns an array of database columns names, that are used to store fake values.
     * Returns ['extras'] if no columns have been found.
     */
    protected function getFakeColumnsAsArray()
    {
        $this->prepareFields();

        $fake_field_columns_to_encode = [];

        if (isset($this->data['crud']['is_translate']) && $this->data['crud']['is_translate'] == true && isset($this->crud['fields']['translate'])) {
            foreach ($this->crud['fields']['translate'] as $k => $fieldArray) {
                foreach ($fieldArray as $e => $field) {
                    // if it's a fake field
                    if (isset($this->crud['fields']['translate'][$k][$e]['fake']) && $this->crud['fields']['translate'][$k][$e]['fake'] == true) {
                        // add it to the request in its appropriate variable - the one defined, if defined

                        if (isset($this->crud['fields']['translate'][$k][$e]['store_in'])) {
                            if (!in_array($this->crud['fields']['translate'][$k][$e]['store_in'].'_trans', $fake_field_columns_to_encode, true)) {
                                array_push($fake_field_columns_to_encode, $this->crud['fields']['translate'][$k][$e]['store_in'].'_trans');
                            }
                        } else {
                            //otherwise in the one defined in the $crud variable

                            if (!in_array('extras', $fake_field_columns_to_encode, true)) {
                                array_push($fake_field_columns_to_encode, 'extras_trans');
                            }
                        }
                    }
                }
            }
            if (isset($this->crud['fields']['normal'])) {
                foreach ($this->crud['fields']['normal'] as $k => $field) {
                    // if it's a fake field

                    if (isset($this->crud['fields']['normal'][$k]['fake']) && $this->crud['fields']['normal'][$k]['fake'] == true) {
                        // add it to the request in its appropriate variable - the one defined, if defined

                        if (isset($this->crud['fields']['normal'][$k]['store_in'])) {
                            if (!in_array($this->crud['fields']['normal'][$k]['store_in'], $fake_field_columns_to_encode, true)) {
                                array_push($fake_field_columns_to_encode, $this->crud['fields']['normal'][$k]['store_in']);
                            }
                        } else {
                            //otherwise in the one defined in the $crud variable

                            if (!in_array('extras', $fake_field_columns_to_encode, true)) {
                                array_push($fake_field_columns_to_encode, 'extras');
                            }
                        }
                    }
                }
            }
        } else {
            foreach ($this->crud['fields'] as $k => $field) {
                // if it's a fake field
                if (isset($this->crud['fields'][$k]['fake']) && $this->crud['fields'][$k]['fake'] == true) {
                    // add it to the request in its appropriate variable - the one defined, if defined

                    if (isset($this->crud['fields'][$k]['store_in'])) {
                        if (!in_array($this->crud['fields'][$k]['store_in'], $fake_field_columns_to_encode, true)) {
                            array_push($fake_field_columns_to_encode, $this->crud['fields'][$k]['store_in']);
                        }
                    } else {
                        //otherwise in the one defined in the $crud variable

                        if (!in_array('extras', $fake_field_columns_to_encode, true)) {
                            array_push($fake_field_columns_to_encode, 'extras');
                        }
                    }
                }
            }
        }

        if (!count($fake_field_columns_to_encode)) {
            return ['extras'];
        }

        return $fake_field_columns_to_encode;
    }

    // If it's not an array of array and it's a simple array, create a proper array of arrays for it
    protected function prepareColumns()
    {
        // if the columns aren't set, we can't show this page
        // TODO: instead of dying, show the columns defined as visible on the model
        if (!isset($this->crud['columns'])) {
            abort(500, 'CRUD columns are not defined.');
        }

        // if the columns are defined as a string, transform it to a proper array
        if (!is_array($this->crud['columns'])) {
            $current_columns_array = explode(',', $this->crud['columns']);

            $proper_columns_array = [];

            foreach ($current_columns_array as $key => $col) {
                $proper_columns_array[] = [
                    'name'  => $col,
                    'label' => ucfirst($col), //TODO: also replace _ with space
                ];
            }

            $this->crud['columns'] = $proper_columns_array;
        }
    }

    /**
     * Prepare the fields to be shown, stored, updated or created.
     *
     * Makes sure $this->crud['fields'] is in the proper format (array of arrays);
     * Makes sure $this->crud['fields'] also contains the id of the current item;
     * Makes sure $this->crud['fields'] also contains the values for each field;
     */
    protected function prepareFields($entry = false)
    {
        // if the fields have been defined separately for create and update, use that

        if (!isset($this->crud['fields'])) {
            if (isset($this->crud['create_fields'])) {
                $this->crud['fields'] = $this->crud['create_fields'];
            } elseif (isset($this->crud['update_fields'])) {
                $this->crud['fields'] = $this->crud['update_fields'];
            }
        }

        // PREREQUISITES CHECK:
        // if the fields aren't set, trigger error
        if (!isset($this->crud['fields'])) {
            abort(500, 'The CRUD fields are not defined.');
        }

        $languages = false;
        if (isset($this->data['crud']['is_translate'])) {
            if ($this->data['crud']['is_translate']) {
                $locales = new $this->locale();
                $languages = $locales->getAvailables();
            }
        }

        // if the fields are defined as a string, transform it to a proper array
        if (!is_array($this->crud['fields'])) {
            $current_fields_array = explode(',', $this->crud['fields']);
            $proper_fields_array = [];

            foreach ($current_fields_array as $key => $field) {
                if ($languages) {
                    foreach ($languages as $lang) {
                        $proper_fields_array[$lang] = [
                            'name'  => $field,
                            'label' => ucfirst($field), // TODO: also replace _ with space
                            'type'  => 'text', // TODO: choose different types of fields depending on the MySQL column type
                        ];
                    }
                } else {
                    $proper_fields_array[] = [
                        'name'  => $field,
                        'label' => ucfirst($field), // TODO: also replace _ with space
                        'type'  => 'text', // TODO: choose different types of fields depending on the MySQL column type
                    ];
                }
            }

            $this->crud['fields'] = $proper_fields_array;
        }

        // if no field type is defined, assume the "text" field type
        foreach ($this->crud['fields'] as $k => $field) {
            //Set label to proper Case
            // if (! isset($this->crud['fields'][$k]['label'])) {
            //     $this->crud['fields'][$k]['label'] = ucfirst($field['label']);
            // }
            if (!isset($field['type'])) {
                $this->crud['fields'][$k]['type'] = 'text';
            }
        }

        if ($languages) {
            $current_fields_array = $this->crud['fields'];
            $proper_fields_array = [];

            foreach ($current_fields_array as $key => $field) {
                foreach ($languages as $lang) {
                    if (isset($field['translate']) && $field['translate'] == true) {
                        $proper_fields_array['translate'][$lang->iso][] = $field;
                    } else {
                        $proper_fields_array['normal'][$key] = $field;
                    }
                }
            }

            //Add Languages
            $this->crud['fields'] = $proper_fields_array;
        }

        // if an entry was passed, we're preparing for the update form, not create
        if ($entry) {
            if ($languages) {
                $fields = $this->getFields();

                if (isset($fields['normal'])) {
                    // set the value
                    foreach ($fields['normal'] as $k => $field) {
                        if (isset($field['type'])) {
                            if (!isset($field['value']) && isset($field['name'])) {
                                $fields['normal'][$k]['value'] = $entry->{$field['name']};
                            }
                        } else {
                            unset($fields['normal'][$k]);
                        }
                    }
                }
                if (isset($fields['translate'])) {
                    foreach ($fields['translate'] as $lang => $fieldArray) {
                        if (is_array($fieldArray)) {
                            foreach ($fieldArray as $e => $field) {
                                if (!isset($field['value'])) {
                                    if (!null === $entry->translate($lang)) {
                                        if (isset($field['fake']) && $field['fake'] == true) {
                                            $fields['translate'][$lang][$e]['value'] = $this->getTranslationFake($entry, $field, $lang, $field['name']);
                                        } else {
                                            $fields['translate'][$lang][$e]['value'] = $entry->translate($lang)->{$field['name']};
                                        }
                                    }
                                }
                            }
                        } else {
                            unset($fields['translate'][$lang]);
                        }
                    }
                }
                $this->crud['fields'] = $fields;

                // always have a hidden input for the entry id
                $this->crud['fields']['normal'][] = [
                    'name'  => 'id',
                    'value' => $entry->id,
                    'type'  => 'hidden',
                ];
            } else {
                $fields = $this->crud['fields'];

                foreach ($fields as $k => $field) {
                    // set the value
                    $this->crud['fields'][$k]['label'] = ucfirst($this->crud['fields'][$k]['label']);
                    if (!isset($this->crud['fields'][$k]['value'])) {
                        if (!isset($field['entity'])) {
                            $this->crud['fields'][$k]['value'] = $entry->{$field['name']};
                        } else {
                            $results = $entry->{$field['entity']}()->getResults();
                            $this->crud['fields'][$k]['value'] = $results;
                        }
                    }
                }

                // always have a hidden input for the entry id
                $this->crud['fields'][] = [
                    'name'  => 'id',
                    'value' => $entry->id,
                    'type'  => 'hidden',
                ];
            }

            // put the values in the same 'fields' variable
        }
    }

    /**
     * Return correct field array.
     *
     * @return mixed
     */
    private function getFields()
    {
        if (isset($this->data['crud']['is_translate']) && $this->data['crud']['is_translate'] == true) {
            if (isset($this->crud['fields']['normal']['normal'])) {
                return $this->crud['fields']['normal'];
            } elseif (isset($this->crud['fields']['normal']['translate'])) {
                return $this->crud['fields']['normal'];
            } else {
                return $this->crud['fields'];
            }
        } else {
            return $this->crud['fields'];
        }
    }

    /**
     * @param $entry
     * @param $field
     * @param $locale
     * @param $column
     *
     * @return json/null
     */
    private function getTranslationFake($entry, $field, $locale, $column)
    {
        if (isset($field['store_in'])) {
            $col = $field['store_in'].'_trans';
            $data = $entry->translate($locale)->$col;
        } else {
            $data = $entry->translate($locale)->extras_trans;
        }
        $json = json_decode($data, true);
        if (isset($json[$column])) {
            return $json[$column];
        } else {
            return;
        }
    }

    /**
     * Check if crud has media attribute.
     *
     * @return bool
     */
    private function hasMedia($fields)
    {
        foreach ($fields as $k => $field) {
            if (isset($field['usemedia']) && $field['usemedia'] == true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process media images. Remember to create a Listener to save model when queue ends.
     *
     * @param  array
     * @param  array
     * @param  Eloquent Model
     */
    public function processMedia($fields, $values_to_store, $model)
    {
        foreach ($fields as $k => $field) {
            if (isset($field['usemedia']) && $field['usemedia'] == true) {
                $name = $field['name'];
                $imagesMedia = $model->getMedia($name);

                $image = $values_to_store[$name];

                $existe = false;
                foreach ($imagesMedia as $imageMedia) {
                    if (asset($imageMedia->getUrl()) === $image) {
                        $existe = true;
                    }
                }
                if ($existe == false) {
                    if (filter_var($image, FILTER_VALIDATE_URL) != false) {
                        //Clean old media files
                        $model->clearMediaCollection($name);

                        //Process new file
                        $model->addMediaFromUrl($image)
                            ->preservingOriginal()
                            ->withCustomProperties(['setModel' => true])
                            ->toCollection($name);
                    }
                }

                //IMPORTANT -> Image will be add to model by MediaLogger event
            }
        }
    }

    /**
     * @param $values_to_store
     *
     * @return mixed
     */
    public function hasFilesToUpload($values_to_store)
    {
        $fields = $this->getFields();

        //Only upload files for normal fields, not translated ;)
        if (isset($fields['normal'])) {
            $fields = $fields['normal'];
        }

        foreach ($fields as $k => $field) {
            if (isset($field['type']) && ($field['type'] == 'image' || $field['type'] == 'upload' || $field['type'] == 'browse')) {
                $filesToUpload = \Request::file($field['name']);

                $fileCount = count($filesToUpload);

                if ($fileCount != 0) {
                    if ($fileCount > 1) {
                        $fieldNames = '';

                        //Multiple Upload
                        foreach ($fileToUpload as $file) {
                            if (!null === $file) {
                                $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                $name = filter_var($name, FILTER_SANITIZE_STRING);
                                $name = $this->sanitize($name).'.'.$file->getClientOriginalExtension();
                                $folder = $this->getUploadFolder();
                                $uploadNames .= $this->uploadFile($folder, $file, $name, true, $field).'||';
                            }
                        }
                    } else {
                        //Single Upload

                        $name = pathinfo($filesToUpload->getClientOriginalName(), PATHINFO_FILENAME);
                        $name = filter_var($name, FILTER_SANITIZE_STRING);
                        $name = $this->sanitize($name).'.'.$filesToUpload->getClientOriginalExtension();
                        $folder = $this->getUploadFolder();
                        $uploadNames = $this->uploadFile($folder, $filesToUpload, $name, true, $field);
                    }

                    $values_to_store[$field['name']] = $uploadNames;
                }

                //$fileExploded=array_filter(explode('||',$fieldNames));
                //dd($fileExploded);
            }
        }

        return $values_to_store;
    }

    /**
     * Return upload folder.
     *
     * @return string
     */
    private function getUploadFolder()
    {
        if (!isset($this->crud['upload_folder'])) {
            $folder = '';
        } else {
            $folder = $this->crud['upload_folder'];
        }

        return $folder;
    }

    /**
     * @param $folder
     * @param $file
     * @param $name
     * @param $checkExistsFile
     * @param false $field
     * @return mixed
     */
    private function uploadFile($folder, $file, $name, $checkExistsFile = false, $field = null)
    {
        if ($checkExistsFile && isset($field['value'])) {
            if (Storage::disk('upload')->exists($field['value'])) {
                //                $file = str_replace('ficha_tecnica/', '', $field['value']);
                $mime = Storage::disk('upload')->getMetaData($field['value']); //For if is a directory. Only for files
                if ($mime['type'] == 'file') {
                    Storage::disk('upload')->delete($field['value']);
                }
            }
        }

        if (!Storage::disk('upload')->exists($folder)) {
            Storage::disk('upload')->makeDirectory($folder, 0777);
        }

        if (is_string($file)) {
            $file = \Request::file($field['name']);
        }

        if (!is_string($file)) {
            Storage::disk('upload')->put($folder.'/'.$name, File::get($file));
        }

        return $folder.'/'.$name;
    }

    /**
     * @param $item
     */
    private function checkHasImagesToDelete($item)
    {
        $fields = $this->getFields();

        //Only upload files for normal fields, not translated ;)
        if (isset($fields['normal'])) {
            $fields = $fields['normal'];
        }

        foreach ($fields as $k => $field) {
            if (isset($field['type']) && ($field['type'] == 'image' || $field['type'] == 'upload')) {
                if (Storage::disk('upload')->exists($item[$field['name']])) {
                    $mime = Storage::disk('upload')->getMetaData($item[$field['name']]); //For if is a directory. Only for files
                    if ($mime['type'] == 'file') {
                        Storage::disk('upload')->delete($item[$field['name']]);
                    }
                }
            }
        }
    }

    /**
     * @param $string
     * @param $forceLowercase
     * @param true $noAccents
     */
    public function sanitize($string, $forceLowercase = true, $noAccents = true)
    {
        $strip = ['~', '`', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '_', '=', '+', '[', '{', ']',
            '}', '\\', '|', ';', ':', '"', "'", '&#8216;', '&#8217;', '&#8220;', '&#8221;', '&#8211;', '&#8212;',
            'â€”', 'â€“', ',', '<', '.', '>', '/', '?'];
        $clean = trim(str_replace($strip, '', strip_tags($string)));
        $clean = preg_replace('/\s+/', '-', $clean);
        $clean = ($noAccents) ? preg_replace('/[^a-zA-Z0-9]/', '', $clean) : $clean;

        return ($forceLowercase) ?
        (function_exists('mb_strtolower')) ?
        mb_strtolower($clean, 'UTF-8') :
        strtolower($clean) :
        $clean;
    }
}
