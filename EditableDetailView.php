<?php
/**
 * EditableDetailView class file.
 * 
 * @author Vitaliy Potapov <noginsk@rambler.ru>
 * @link https://github.com/vitalets/x-editable-yii
 * @copyright Copyright &copy; Vitaliy Potapov 2012
 * @version 1.0.0
*/
 
require_once 'EditableField.php';
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
            //disable loading Yii's css for bootstrap
            $this->cssFile = false;
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

            //take common url if not defined for particular item and not related model
            if (!isset($options['editable']['url']) && strpos($options['name'], '.') === false) {
                $options['editable']['url'] = $this->url;
            }
            
            //take common params if not defined for particular item 
            if (!isset($options['editable']['params'])) {
                $options['editable']['params'] = $this->params;
            }            

            //option to be passed into EditableField
            $widgetOptions = array(
                'model'     => $this->data,
                'attribute' => $options['name']
            );
            
            //if value in detailview options provided, set text directly (as value here means text)
            if(isset($options['value']) && $options['value'] !== null) {
                $widgetOptions['text'] = $templateData['{value}'];
                $widgetOptions['encode'] = false;
            }            
            
            $widgetOptions = CMap::mergeArray($widgetOptions, $options['editable']);

            $widget = $this->controller->createWidget('EditableField', $widgetOptions);
            
            //'apply' can be changed during init of widget (e.g. if related model and unsafe attribute)
            if($widget->apply) {
                ob_start();
                $widget->run();
                $templateData['{value}'] = ob_get_clean();
            }
        } 

        parent::renderItem($options, $templateData);
    }

}

