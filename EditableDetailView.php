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
    
    //todo: add params property

    public function init()
    {
        if (!$this->data instanceof CModel) {
            throw new CException('Property "data" should be of CModel class.');
        }

        //set bootstrap css
        if(yii::app()->editable->form === EditableComponent::FORM_BOOTSTRAP) {
            $this->htmlOptions = array('class'=> 'table table-bordered table-striped table-hover table-condensed');
        }
        
        parent::init();
    }

    protected function renderItem($options, $templateData)
    {
        //apply editable if not set 'editable' params or set and not false
        $apply = !empty($options['name']) && (!isset($options['editable']) || $options['editable'] !== false); 
        
        if ($apply) {    
            //ensure $options['editable'] is array
            if(!array_key_exists('editable', $options) || !is_array($options['editable'])) $options['editable'] = array();

            //take common url if not defined for particular item and not related model
            if (!array_key_exists('url', $options['editable']) && strpos($options['name'], '.') === false) {
                $options['editable']['url'] = $this->url;
            }

            $editableOptions = CMap::mergeArray($options['editable'], array(
                'model'     => $this->data,
                'attribute' => $options['name'],
                'emptytext' => ($this->nullDisplay === null) ? Yii::t('zii', 'Not set') : strip_tags($this->nullDisplay),
            ));
            
            //if value in detailview options provided, set text directly
            if(array_key_exists('value', $options) && $options['value'] !== null) {
                $editableOptions['text'] = $templateData['{value}'];
                $editableOptions['encode'] = false;
            }

            $widget = $this->controller->createWidget('EditableField', $editableOptions);
            
            //'apply' can be changed during init of widget
            if($widget->apply) {
                ob_start();
                $widget->run();
                $templateData['{value}'] = ob_get_clean();
            }
        } 

        parent::renderItem($options, $templateData);
    }

}

