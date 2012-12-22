<?php
/**
 * EditableField class file.
 * 
 * @author Vitaliy Potapov <noginsk@rambler.ru>
 * @link https://github.com/vitalets/x-editable-yii
 * @copyright Copyright &copy; Vitaliy Potapov 2012
 * @version 1.0.0
*/

/**
* EditableField widget makes editable single attribute of model. 
* 
* @package widgets
*/
class EditableField extends CWidget
{
    //note: only most usefull options are on first config level. 
    
    // --- start of X-editable options ----
    
    /**
    * @var CActiveRecord model of attribute to edit.
    */
    public $model = null;
    /**
    * @var string attribute name.
    */
    public $attribute = null;
    /**
    * @var string type of editable widget. Can be 'text', 'textarea', 'select' etc.
    */
    public $type = null;
    /**
    * @var string url to submit value
    */
    public $url = null;
    /**
    * @var array additional params to send on server
    */
    public $params = null;
    /**
    * @var string css class of input
    */
    public $inputclass = null;    
    /**
    * @var string text to be shown as element content
    */
    public $text = null;
    /**
    * @var mixed initial value. If not set - will be take from text
    */
    public $value = null;
    /**
    * @var string placement of popup. Can be 'left', 'top', 'right', 'bottom'
    */
    public $placement = 'top';
    
    /**
    * @var string text shown on empty field
    */
    public $emptytext;
    
    /**
    * @var boolean will editable be initially disabled. It means editable plugin will be applied to element anyway.
    * To disable applying 'editable' to element use 'apply' option
    */
    public $disabled = false;
   
    //list
    /**
    * @var mixed source data for 'select', 'checklist'. Can be url or php array.
    */
    public $source = null;

    //date
    /**
    * @var string format to send date on server
    */
    public $format = 'yyyy-mm-dd';
    /**
    * @var string format to display date in element
    */
    public $viewformat = null;

    //methods
    /**
    * @var string a javascript function that will be invoked to validate value.
    */
    public $validate = null;
    
    // --- X-editable events ---
    /**
    * @var string a javascript function that will be invoked when editable element is initializd
    */    
    public $onInit;
    /**
    * @var string a javascript function that will be invoked when editable form is shown
    */    
    public $onShown;
    /**
    * @var string a javascript function that will be invoked when new value is saved
    */    
    public $onSave;
    /**
    * @var string a javascript function that will be invoked when editable form is hidden
    */    
    public $onHidden;
    
    /**
    * @var array all config options of x-editable
    */
    public $options = array();
    
    /**
    * @var array HTML options of element
    */
    public $htmlOptions = array();

    /**
    * @var boolean whether to HTML encode text on output
    */
    public $encode = true;
    
    /**
    * @var boolean whether to apply 'editable' to element. 
    * If null will be automatically set to true for safe attributes and false for unsafe.
    */
    public $apply = null; 
    
    /**
    * @var string title of popup. If null will be generated automatically from attribute label.
    * Can have token {label} inside that will be replaced with actual attribute label.
    */
    public $title = null;

    private $_prepareToAutotext = false; 
    
    /**
    * initialization of widget
    * 
    */
    public function init()
    {   
        parent::init();
        
        if($this->apply === false) {
            return;
        }
        
        if (!$this->model) {
            throw new CException('Parameter "model" should be provided for Editable');
        }
        if (!$this->attribute) {
            throw new CException('Parameter "attribute" should be provided for Editable');
        }
        
        //resolve model and attribute for related model
        $resolved = self::resolveModel($this->model, $this->attribute);    
        if($resolved === false) {
            $this->apply = false;
            return;
        } else {
            list($this->model, $this->attribute) = $resolved;
        }       
        
        //commented to be able to work with virtual attributes
        //see https://github.com/vitalets/yii-bootstrap-editable/issues/15
        /*
        if (!$this->model->hasAttribute($this->attribute)) {
            throw new CException('Model "'.get_class($this->model).'" does not have attribute "'.$this->attribute.'"');
        } 
        */          

        //if `apply` not defined directly, set it to true only for safe attributes
        if($this->apply === null) {
            $this->apply = $this->model->isAttributeSafe($this->attribute);
        }
        
        //if apply = false --> just print text (see 'run' method)
        if ($this->apply === false) {
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
        $this->_prepareToAutotext = (!isset($this->options['autotext']) || $this->options['autotext'] !== 'never') && in_array($this->type, array('select', 'checklist', 'date', 'dateui'));

        /*
         unfortunatly datepicker's format does not match Yii locale dateFormat
         and we cannot take format from application locale
         
         see http://www.unicode.org/reports/tr35/#Date_Format_Patterns
         
        if($this->type == 'date' && $this->format === null) {
            $this->format = Yii::app()->locale->getDateFormat();
        }
        */
        
        /* 
         generate text from model attribute. 
         For all types except 'select', 'checklist' etc.  For these keep it empty to apply autotext
        */ 
        if (!strlen($this->text) && !$this->_prepareToAutotext) {
            $this->text = CHtml::value($this->model, $this->attribute);
        }
                     
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
            'data-pk'   => $this->model->primaryKey,
        );

        //if preparing to autotext we need to define value directly in data-value.
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
        $options = array(
            'type'  => $this->type,
            'url'   => $this->url,
            'name'  => $this->attribute,
            'title' => CHtml::encode($this->title),
        );

        //language for datepicker: use yii config's value if not defined directly
        if (isset($this->options['datepicker']) && !$this->options['datepicker']['language'] && yii::app()->language) {
            $this->options['datepicker']['language'] = yii::app()->language;
        }        
        
        if ($this->placement) {
            $options['placement'] = $this->placement;
        }
        
        if ($this->emptytext) {
            $options['emptytext'] = $this->emptytext;
        }
        
        if ($this->params) {
            $options['params'] = $this->params;
        }        
        
        if ($this->inputclass) {
            $options['inputclass'] = $this->inputclass;
        }         

        if ($this->source) {
            if(is_array($this->source) && count($this->source)) {
                //if first elem is array assume it's normal x-editable format, so just pass it
                if(isset($this->source[0]) && is_array($this->source[0])) {
                    $options['source'] = $this->source;
                } else { //else convert to x-editable source format
                    $options['source'] = array();
                    foreach($this->source as $value => $text) {
                        $options['source'][] = array('value' => $value, 'text' => $text);  
                    }
                }
            } else {
                $options['source'] = $this->source;
            }
        } 
        
        if ($this->format) {
            $options['format'] = $this->format;
        }
        if ($this->viewformat) {
            $options['viewformat'] = $this->viewformat;
        }                   

        //callbacks
        foreach(array('validate', 'success', 'display') as $c) {
            if(isset($this->options[$c])) {
                $options[$c]=(strpos($this->options[$c], 'js:') !== 0 ? 'js:' : '') . $this->options[$c];
            }
        }        

        //merging options
        $this->options = CMap::mergeArray($this->options, $options);
    }

    public function registerClientScript()
    {
        $script = "$('a[rel={$this->htmlOptions['rel']}]')";
          
        //attach events
        foreach(array('init', 'shown', 'save', 'hidden') as $event) {
            $eventName = 'on'.ucfirst($event);
            if (isset($this->$eventName)) {
                // CJavaScriptExpression appeared only in 1.1.11, will turn to it later
                //$event = ($this->onInit instanceof CJavaScriptExpression) ? $this->onInit : new CJavaScriptExpression($this->onInit);
                $eventJs = (strpos($this->$eventName, 'js:') !== 0 ? 'js:' : '') . $this->$eventName;
                $script .= "\n.on('".$event."', ".CJavaScript::encode($eventJs).")";
            }
        }

        //apply editable
        $options = CJavaScript::encode($this->options);        
        $script .= ".editable($options);";
        
        Yii::app()->getClientScript()->registerScript(__CLASS__ . '#' . $this->id, $script);
        
        return $script;
    }

    public function registerAssets()
    {
        //if bootstrap extension installed --> use it!
        if(yii::app()->editable->form === EditableComponent::FORM_BOOTSTRAP) {
            if (($bootstrap = yii::app()->getComponent('bootstrap'))) {
                $bootstrap->registerCoreCss();
                $bootstrap->registerCoreScripts();
            }
            
            //publish x-editable assets for bootstrap
            $assetsUrl = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('editable.assets.bootstrap-editable')); 
            $js = yii::app()->editable->container === EditableComponent::POPUP ? 'bootstrap-editable.js' : 'bootstrap-editable-inline.js';
            $css = 'bootstrap-editable.css';
        }
        
        //register assets            
        Yii::app()->getClientScript()->registerCssFile($assetsUrl . '/css/'.$css);
        Yii::app()->clientScript->registerScriptFile($assetsUrl . '/js/'.$js, CClientScript::POS_END);
        

        //TODO: include locale for datepicker
        /*
        if ($this->type == 'date' && $this->language && substr($this->language, 0, 2) != 'en') {
             //todo: check compare dp locale name with yii's
             $localesUrl = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('ext.editable.assets.js.locales'));
             Yii::app()->clientScript->registerScriptFile($localesUrl . '/bootstrap-datepicker.'. str_replace('_', '-', $this->language).'.js', CClientScript::POS_END);
        }
        */
    }

    public function run()
    {
        if($this->apply) {
            $this->registerClientScript();
            $this->renderLink();
        } else {
            $this->renderText();
        }
    }

    public function renderLink()
    {
        echo CHtml::openTag('a', $this->htmlOptions);
        $this->renderText();
        echo CHtml::closeTag('a');
    }

    public function renderText()
    {   
        $encodedText = $this->encode ? CHtml::encode($this->text) : $this->text;
        if($this->type == 'textarea') {
             $encodedText = preg_replace('/\r?\n/', '<br>', $encodedText);
        }
        echo $encodedText;
    }    
    
    public function getSelector()
    {
        return get_class($this->model) . '_' . $this->attribute . ($this->model->primaryKey ? '_' . $this->model->primaryKey : '_new');
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
