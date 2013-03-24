<?php
/**
 * EditableField class file.
 *
 * @author Vitaliy Potapov <noginsk@rambler.ru>
 * @link https://github.com/vitalets/x-editable-yii
 * @copyright Copyright &copy; Vitaliy Potapov 2012
 * @version 1.1.0
*/

/**
* EditableField widget makes editable single attribute of model.
*
* @package widgets
*/
class EditableField extends Editable
{
    //note: only most usefull options are on first level of config.

    // --- start of X-editable options ----
    /**
    * @var CActiveRecord ActiveRecord to be updated.
    */
    public $model = null;
    /**
    * @var string attribute name.
    */
    public $attribute = null;
 
    /**
    * initialization of widget
    *
    */
    public function init()
    {
        parent::init();

        if (!$this->model) {
            throw new CException('Parameter "model" should be provided for EditableField');
        }

        if (!$this->attribute) {
            throw new CException('Parameter "attribute" should be provided for EditableField');
        }

        $originalText = strlen($this->text) ? $this->text : CHtml::value($this->model, $this->attribute);

        //if apply set manually to false --> just render text, no js plugin applied
        if($this->apply === false) {
            $this->text = $originalText;
            return;
        } else {
            $this->apply = true;
        }

        //resolve model and attribute for related model
        $resolved = self::resolveModel($this->model, $this->attribute);
        if($resolved === false) {
            //cannot resolve related model (maybe no related models for this record)
            $this->apply = false;
            $this->text = $originalText;
            return;
        } else {
            list($this->model, $this->attribute) = $resolved;
        }

        //for security reason only safe attributes can be editable (e.g. defined in rules of model)
        //just print text (see 'run' method)
        if (!$this->model->isAttributeSafe($this->attribute)) {
            $this->apply = false;
            $this->text = $originalText;
            return;
        }

        /*
         try to detect type from metadata if not set
        */
        if ($this->type === null) {
            $this->type = 'text';
            if (array_key_exists($this->attribute, $this->model->tableSchema->columns)) {
                $dbType = $this->model->tableSchema->columns[$this->attribute]->dbType;
                if($dbType == 'date' || $dbType == 'datetime') $this->type = 'date';
                if(stripos($dbType, 'text') !== false) $this->type = 'textarea';
            }
        }

        /*
        If set this flag to true --> element content will stay empty and value will be rendered to data-value attribute to apply autotext.
        */
        $this->_prepareToAutotext = (!isset($this->options['autotext']) || $this->options['autotext'] !== 'never') 
         && in_array($this->type, array('select', 'checklist', 'date', 'dateui', 'combodate', 'select2'));

        /*
         If text not defined, generate it from model attribute for types except lists ('select', 'checklist' etc)
         For lists keep it empty to apply autotext
        */
        if (!strlen($this->text) && !$this->_prepareToAutotext) {
            $this->text = $originalText;
        }

        $this->buildHtmlOptions();
        $this->buildJsOptions();
        $this->registerAssets();
    }

    public function buildHtmlOptions()
    {
        //html options
        $htmlOptions = array(
            'href'      => '#',
            'rel'       => $this->getSelector(),
        );

        //set data-pk only for existing records
        if(!$this->model->isNewRecord) {
           $htmlOptions['data-pk'] = is_array($this->model->primaryKey) ? CJSON::encode($this->model->primaryKey) : $this->model->primaryKey; 
        }

        //if input type assumes autotext (e.g. select) we define value directly in data-value 
        //and do not fill element contents
        if ($this->_prepareToAutotext) {
            //for date we use 'format' to put it into value (if text not defined)
            if ($this->type == 'date') {
                $this->value = $this->model->getAttribute($this->attribute);

                //if date comes as object, format it to string
                if($this->value instanceOf DateTime) {
                    /*
                    * unfortunatly datepicker's format does not match Yii locale dateFormat,
                    * we need replacements below to convert date correctly
                    */
                    $count = 0;
                    $format = str_replace('MM', 'MMMM', $this->format, $count);
                    if(!$count) $format = str_replace('M', 'MMM', $format, $count);
                    if(!$count) $format = str_replace('m', 'M', $format);

                    $this->value = Yii::app()->dateFormatter->format($format, $this->value->getTimestamp());
                }
            } else {
                $this->value = $this->model->getAttribute($this->attribute);
            }

            $this->htmlOptions['data-value'] = $this->value;
        }

        //merging options
        $this->htmlOptions = CMap::mergeArray($this->htmlOptions, $htmlOptions);
    }

    public function buildJsOptions()
    {
        //normalize url from array
        $this->url = CHtml::normalizeUrl($this->url);

        //generate title from attribute label
        if ($this->title === null) {
            $titles = array(
              'Select' => array('select', 'date'),
              'Check' => array('checklist')
            );
            $title = Yii::t('EditableField.editable', 'Enter');
            foreach($titles as $t => $types) {
                if(in_array($this->type, $types)) {
                   $title = Yii::t('EditableField.editable', $t);
                }
            }
            $this->title = $title . ' ' . $this->model->getAttributeLabel($this->attribute);
        } else {
            $this->title = strtr($this->title, array('{label}' => $this->model->getAttributeLabel($this->attribute)));
        }

        $options = array(
            'type'  => $this->type,
            'url'   => $this->url,
            'name'  => $this->attribute,
            'title' => CHtml::encode($this->title),
        );

        //simple options set directly from config
        foreach(array('mode', 'placement', 'emptytext', 'params', 'inputclass', 'format', 'viewformat', 'template',
                      'combodate', 'select2', 'viewseparator'
               ) as $option) {
            if ($this->$option) {
                $options[$option] = $this->$option;
            }
        }

        if ($this->source) {
            //if source is array --> convert it to x-editable format.
            //Since 1.1.0 source as array with one element is NOT treated as Yii route!
            if(is_array($this->source)) {
                //if first elem is array assume it's normal x-editable format, so just pass it
                if(isset($this->source[0]) && is_array($this->source[0])) {
                    $options['source'] = $this->source;
                } else { //else convert to x-editable source format {value: 1, text: 'abc'}
                    $options['source'] = array();
                    foreach($this->source as $value => $text) {
                        $options['source'][] = array('value' => $value, 'text' => $text);
                    }
                }
            } else { //source is url
                $options['source'] = CHtml::normalizeUrl($this->source);
            }
        }

        //TODO: language for datepicker: use yii config's value if not defined directly

        /*
         unfortunatly datepicker's format does not match Yii locale dateFormat
         so we cannot take format from application locale

         see http://www.unicode.org/reports/tr35/#Date_Format_Patterns

        if($this->type == 'date' && $this->format === null) {
            $this->format = Yii::app()->locale->getDateFormat();
        }
        */
        /*
        if (isset($this->options['datepicker']) && !$this->options['datepicker']['language'] && yii::app()->language) {
            $this->options['datepicker']['language'] = yii::app()->language;
        }
        */

        //callbacks
        foreach(array('validate', 'success', 'display') as $method) {
            if(isset($this->$method)) {
                $options[$method]=(strpos($this->$method, 'js:') !== 0 ? 'js:' : '') . $this->$method;
            }
        }

        //merging options
        $this->options = CMap::mergeArray($this->options, $options);
    }

    public function getSelector()
    {
        if($this->model->isNewRecord) {
            $pk = 'new';
        } else {
            $pk = $this->model->primaryKey;
            //support of composite keys: convert to string
            if(is_array($pk)) {
                $pk = join('_', array_map(function($k, $v) { return $k.'-'.$v; }, array_keys($pk), $pk));
            }       
        }
         
        return str_replace('\\', '_', get_class($this->model)).'_'.$this->attribute.'_'.$pk;
    }
   
    /**
    * check if attribute points to related model and resolve it
    *
    * @param mixed $model
    * @param mixed $attribute
    */
    public static function resolveModel($model, $attribute)
    {
        $explode = explode('.', $attribute);
        if(count($explode) > 1) {
            for($i = 0; $i < count($explode)-1; $i++) {
                $name = $explode[$i];
                if($model->$name instanceof CActiveRecord) {
                    $model = $model->$name;
                } else {
                    //related model not exist! Better to return false and render as usual not editable field.
                    //throw new CException('Property "'.$name.'" is not instance of CActiveRecord!');
                    return false;
                }
            }
            $attribute = $explode[$i];
        }
        return array($model, $attribute);
    }
}
