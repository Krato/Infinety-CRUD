<!-- include redactor js-->
<?php
    $assets_url = config('infinety-crud.assets_folder', 'admin_theme');
    $crud_url = config('infinety-crud.crud-route-prefix', 'admin');
?>

<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.js"></script>
<script src="{{ asset($assets_url.'/assets/plugins/redactor/redactor.js') }}"></script>
<script src="{{ asset($assets_url.'/assets/plugins/redactor/lang/es.js') }}"></script>
<script src="{{ asset($assets_url.'/assets/plugins/redactor/lang/en.js') }}"></script>
<script src="{{ asset($assets_url.'/assets/plugins/redactor/plugins/fullscreen.js') }}" type="text/javascript"></script>
<script src="{{ asset($assets_url.'/assets/plugins/redactor/plugins/imagemanager.js') }}" type="text/javascript"></script>
<script src="{{ asset($assets_url.'/assets/plugins/redactor/plugins/video.js') }}" type="text/javascript"></script>
<script src="{{ asset($assets_url.'/assets/plugins/redactor/plugins/source.js') }}" type="text/javascript"></script>

<script>
    @foreach($fields as $language => $field)


        @if( $language === 'lang' )
            @foreach($field as $lang => $fieldsArray)   
                @foreach($fieldsArray as $fieldLang)

                @php
                    $localeRedactor = (in_array($lang, ['es', 'en'])) ? $lang : 'en';
                @endphp

                jQuery(document).ready(function($) {
                    $('#redactor-{{$fieldLang["name"]}}-{{$lang}}').redactor({
                        lang: '{{$localeRedactor}}',
                        minHeight: {{ (isset($fieldLang["height"]) ? $fieldLang["height"] : 350) }},
                        maxHeight: 800,
                        cleanOnPaste: true,
                        cleanSpaces: true,
                        removeComments: true,
                        removeEmpty: ['strong', 'em', 'span', 'p'],
                        buttonsHide: ['orderedlist', 'image'],
                        formatting: ['p', 'blockquote', 'h2', 'h3', 'h4'],
                        plugins: ['source', 'fullscreen',  'video', 'imagemanager'],
                        imageManagerUrl: "{{ url($crud_url.'/filemanager/dialog') }}?type=editor&editor=redactor-{{$fieldLang["name"]}}-{{$lang}}"
                    });
                });
                @endforeach
            @endforeach
        @else
            jQuery(document).ready(function($) {
                $('#redactor-{{$field["name"]}}').redactor({
                    minHeight: {{ (isset($fieldLang["height"]) ? $fieldLang["height"] : 350) }},
                    maxHeight: 800,
                    cleanOnPaste: true,
                    cleanSpaces: true,
                    removeComments: true,
                    removeEmpty: ['strong', 'em', 'span', 'p'],
                    buttonsHide: ['orderedlist', 'image'],
                    formatting: ['p', 'blockquote', 'h2', 'h3', 'h4'],
                    plugins: ['source', 'fullscreen',  'video', 'imagemanager'],
                    imageManagerUrl: "{{ url($crud_url.'/filemanager/dialog') }}?type=editor&editor=redactor-{{$field["name"]}}"
                });
            });
        @endif
    @endforeach
</script>