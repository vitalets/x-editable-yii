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

    // --- end of X-editable options ----
    
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

    /**
    * initialization of widget
    * 
    */
    public function init()
    {   
        if (!$this->model) {
            throw new CException('Parameter "model" should be provided for Editable');
        }
        if (!$this->attribute) {
            throw new CException('Parameter "attribute" should be provided for Editable');
        }
        
        //commented to be able to work with virtual attributes
        //see https://github.com/vitalets/yii-bootstrap-editable/issues/15
        /*
        if (!$this->model->hasAttribute($this->attribute)) {
            throw new CException('Model "'.get_class($this->model).'" does not have attribute "'.$this->attribute.'"');
        } 
        */       
 
        parent::init();

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
        $keepEmpty = array('select', 'checklist', 'date', 'dateui');
        if (!strlen($this->text) && !in_array($this->type, $keepEmpty)) {
            $this->text = $this->model->getAttribute($this->attribute);
        }

        //if `apply` not defined directly, set it to true only for safe attributes
        if($this->apply === null) {
            $this->apply = $this->model->isAttributeSafe($this->attribute);
        }
        
        //if apply = false --> just print text and return       
        if (!$this->apply) {
            return;
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

        //for select we need to define value directly
        if ($this->type == 'select') {
            $this->value = $this->model->getAttribute($this->attribute);
            $this->htmlOptions['data-value'] = $this->value;
        }
        
        //for date we use 'format' to put it into value (if text not defined)
        if ($this->type == 'date' && !strlen($this->text)) {
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
        if ($this->options['datepicker'] && !$this->options['datepicker']['language'] && yii::app()->language) {
            $this->options['datepicker']['language'] = yii::app()->language;
        }        
        
        if ($this->emptytext) {
            $options['emptytext'] = $this->emptytext;
        }
        
        if ($this->placement) {
            $options['placement'] = $this->placement;
        }
        
        if ($this->inputclass) {
            $options['inputclass'] = $this->inputclass;
        }    
        
        if ($this->autotext) {
            $options['autotext'] = $this->autotext;
        }            

        switch ($this->type) {
            case 'text':
            case 'textarea':
                if ($this->placeholder) {
                    $options['placeholder'] = $this->placeholder;
                }
                break;
            case 'select':
                if ($this->source) {
                    $options['source'] = $this->source;
                }
                if ($this->prepend) {
                    $options['prepend'] = $this->prepend;
                }
                break;
            case 'date':
                if ($this->format) {
                    $options['format'] = $this->format;
                }
                if ($this->viewformat) {
                    $options['viewformat'] = $this->viewformat;
                }                
                if ($this->language && substr($this->language, 0, 2) != 'en') {
                    $options['datepicker']['language'] = $this->language;
                }
                if ($this->weekStart !== null) {
                    $options['weekStart'] = $this->weekStart;
                }
                if ($this->startView !== null) {
                    $options['startView'] = $this->startView;
                }
                break;
        }

        //methods
        foreach(array('validate', 'success', 'error') as $event) {
            if($this->$event!==null) {
                $options[$event]=(strpos($this->$event, 'js:') !== 0 ? 'js:' : '') . $this->$event;
            }
        }        

        //merging options
        $this->options = CMap::mergeArray($this->options, $options);
    }

    public function registerClientScript()
    {
        $script = "$('a[rel={$this->htmlOptions['rel']}]')";
          
        //attach events
        foreach(array('init', 'update', 'render', 'shown', 'hidden') as $event) {
            $property = 'on'.ucfirst($event); 
            if ($this->$property) {
                // CJavaScriptExpression appeared only in 1.1.11, will turn to it later
                //$event = ($this->onInit instanceof CJavaScriptExpression) ? $this->onInit : new CJavaScriptExpression($this->onInit);
                $eventJs = (strpos($this->$property, 'js:') !== 0 ? 'js:' : '') . $this->$property;
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
        //if bootstrap extension installed, but no js registered -> register it!
        if (($bootstrap = yii::app()->getComponent('bootstrap')) && !$bootstrap->enableJS) {
            $bootstrap->registerCorePlugins(); //enable bootstrap js if needed
        }

        $assetsUrl = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('ext.editable.assets'), false, 1); //publish excluding datepicker locales
        Yii::app()->getClientScript()->registerCssFile($assetsUrl . '/css/bootstrap-editable.css');
        Yii::app()->clientScript->registerScriptFile($assetsUrl . '/js/bootstrap-editable.js', CClientScript::POS_END);

        //include locale for datepicker
        if ($this->type == 'date' && $this->language && substr($this->language, 0, 2) != 'en') {
             //todo: check compare dp locale name with yii's
             $localesUrl = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('ext.editable.assets.js.locales'));
             Yii::app()->clientScript->registerScriptFile($localesUrl . '/bootstrap-datepicker.'. str_replace('_', '-', $this->language).'.js', CClientScript::POS_END);
        }
    }

    public function run()
    {
        if($this->enabled) {
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
     * method to use i18n messages from extension 'messages' folder.
     * 
     * @param mixed $str
     * @param mixed $params
     * @param mixed $dic
     */
    public static function t($str='', $params=array(), $dic='editable') {
        return Yii::t("EditableField.".$dic, $str, $params);
    }
}
