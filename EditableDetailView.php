<?php
/**
 * EditableDetailView class file.
 *
 * @author Vitaliy Potapov <noginsk@rambler.ru>
 * @link https://github.com/vitalets/x-editable-yii
 * @copyright Copyright &copy; Vitaliy Potapov 2012
 * @version 1.0.0
*/

Yii::import('editable.EditableField');
Yii::import('zii.widgets.CDetailView');

/**
* EditableDetailView widget makes editable CDetailView (several attributes of single model shown as name-value table).
*
* @package widgets
*/
class EditableDetailView extends CDetailView
{
    /**
    * @var string submit url for all editables in detailview
    */
    public $url = null;

    /**
    * @var array additional params to send on server
    */
    public $params = null;

    public function init()
    {
        if (!$this->data instanceof CModel) {
            throw new CException('Property "data" should be of CModel class.');
        }

        //set bootstrap css
        if(yii::app()->editable->form === EditableConfig::FORM_BOOTSTRAP) {
            $this->htmlOptions = array('class'=> 'table table-bordered table-striped table-hover');
        }

        parent::init();
    }

    protected function renderItem($options, $templateData)
    {
        //apply editable if not set 'editable' params or set and not false
        $apply = !empty($options['name']) && (!isset($options['editable']) || $options['editable'] !== false);

        if ($apply) {
            //ensure $options['editable'] is array
            if(!isset($options['editable'])) $options['editable'] = array();

            $options['editable'] = CMap::mergeArray($options['editable'], $this->_data);

            //take common url if not defined for particular item and not related model
            if (!isset($options['editable']['url']) && strpos($options['name'], '.') === false) {
                $options['editable']['url'] = $this->url;
            }

            //take common params if not defined for particular item
            if (!isset($options['editable']['params'])) {
                $options['editable']['params'] = $this->params;
            }

            $editableOptions = CMap::mergeArray($options['editable'], array(
                'model'     => $this->data,
                'attribute' => $options['name'],
                'emptytext' => ($this->nullDisplay === null) ? Yii::t('zii', 'Not set') : strip_tags($this->nullDisplay),
            ));

            //if value in detailview options provided, set text directly (as value means text)
            if(isset($options['value']) && $options['value'] !== null) {
                $editableOptions['text'] = $templateData['{value}'];
                $editableOptions['encode'] = false;
            }

            $widget = $this->controller->createWidget('EditableField', $editableOptions);

            //'apply' can be changed during init of widget (e.g. if related model and unsafe attribute)
            if($widget->apply) {
                ob_start();
                $widget->run();
                $templateData['{value}'] = ob_get_clean();
            }
        }

        parent::renderItem($options, $templateData);
    }

    //***************************************************************************************
    // Generic getter/setter implementation to accept default configuration for EditableField
    //***************************************************************************************

    /** Data for default fields of EditableField */
    private $_data = array();
    /** Valid attributes for EditableField (singleton) */
    private $_editableProperties;

    /**
     * Get the properties available for {@link EditableField}.
     *
     * These properties can also be set for the {@link EditableDetailView} as default values.
     */
    private function getEditableProperties() {
        if(!isset($this->_editableProperties)) {
            $reflection = new ReflectionClass('EditableField');
            $this->_editableProperties = array_map(function($d){return $d->getName();},$reflection->getProperties());
        }
        return $this->_editableProperties;
    }

    /**
     * (non-PHPdoc)
     * @see CComponent::__get()
     */
    public function __get($key) {
        return (array_key_exists($key,$this->_data) ? $this->_data[$key] : parent::__get($key));
    }

    /**
     * (non-PHPdoc)
     * @see CComponent::__set()
     */
    public function __set($key, $value) {
        if(in_array($key,$this->getEditableProperties())) {
            $this->_data[$key] = $value;
        } else {
            parent::__set($key,$value);
        }
    }

    /**
     * (non-PHPdoc)
     * @see CComponent::__isset()
     */
    public function __isset($name) {
        return array_key_exists($name,$this->_data)||parent::__isset($name);
    }
}

