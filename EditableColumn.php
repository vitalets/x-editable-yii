<?php
/**
 * EditableColumn class file.
 *
 * @author Vitaliy Potapov <noginsk@rambler.ru>
 * @link https://github.com/vitalets/x-editable-yii
 * @copyright Copyright &copy; Vitaliy Potapov 2012
 * @version 1.1.0
 */

Yii::import('editable.EditableField');
Yii::import('zii.widgets.grid.CDataColumn');

/**
 * EditableColumn widget makes editable one column in CGridView.
 *
 * @package widgets
 */
class EditableColumn extends CDataColumn
{
    /**
    * @var array editable config options.
    * @see EditableField config
    */
    public $editable = array();

    //flag to render client script only once for all column cells
    private $_isScriptRendered = false;

    public function init()
    {
        /*
        if (!$this->grid->dataProvider instanceOf CActiveDataProvider && !$this->grid->dataProvider instanceOf CArrayDataProvider) {
            throw new CException('EditableColumn can be applied only to a grid based on CActiveDataProvider or CArrayDataProvider');
        }
        */
        
        if (!$this->name) {
            throw new CException('You should provide name for EditableColumn');
        }

        parent::init();

        //need to attach ajaxUpdate handler to refresh editables on pagination and sort
        //should be here, before render of grid js
        $this->attachAjaxUpdateEvent();
    }

    protected function renderDataCellContent($row, $data)
    {
        $isModel = $data instanceOf CModel;
        
        if($isModel) {
            $widgetClass = 'EditableField';
            $options = array(
                'model'     => $data,
                'attribute' => $this->name,
            );   
            //manually make selector non unique to match all cells in column
            $selector = $this->grid->id.'_'.str_replace('\\', '_', get_class($data)).'_'.$this->name;   
            
            //flag to pass text to widget
            $passText = strlen($this->value);     
        } else {
            $widgetClass = 'Editable';
            $options = array(
                'pk'     => $data[$this->grid->dataProvider->keyField],
                'name'   => $this->name
            );
            //manually make selector non unique to match all cells in column
            $selector = $this->grid->id.'_'.$this->name; 
            
            $passText = true;
            //if autotext will be applied, do not pass text param
            if(!strlen($this->value) && Editable::isAutotext($this->editable, isset($this->editable['type']) ? $this->editable['type'] : '')) {
               $options['value'] = $data[$this->name]; 
               $passText = false;
            } 
        }
        
        $options = CMap::mergeArray($this->editable, $options);

        //if value defined for column --> use it as element text
        if($passText) {
            ob_start();
            parent::renderDataCellContent($row, $data);
            $text = ob_get_clean();
            $options['text'] = $text;
            $options['encode'] = false;
        }
        
        //apply may be a string expression, see https://github.com/vitalets/x-editable-yii/issues/33
        if (isset($options['apply']) && is_string($options['apply'])) {
            $options['apply'] = $this->evaluateExpression($options['apply'], array('data'=>$data, 'row'=>$row));
        }           

        $widget = $this->grid->controller->createWidget($widgetClass, $options);

        //if editable not applied --> render original text
        if($widget->apply === false) {
           
           if(isset($text)) {
               echo $text;
           } else {
               parent::renderDataCellContent($row, $data);
           }
           return;
        }

        //call these methods manually as we don't call run()
        $widget->buildHtmlOptions();
        $widget->buildJsOptions();
        $widget->registerAssets();         
        
        //manually make selector non unique to match all cells in column
        //model class may be namespaced, see https://github.com/vitalets/x-editable-yii/issues/9
        $widget->htmlOptions['rel'] = $selector;

        //can't call run() as it registers clientScript
        $widget->renderLink();

        //manually render client script (one for all cells in column)
        if (!$this->_isScriptRendered) {
            $script = $widget->registerClientScript();
            //use parent() as grid is totally replaced by new content
            Yii::app()->getClientScript()->registerScript(__CLASS__ . '#' . $selector.'-event', '
                $("#'.$this->grid->id.'").parent().on("ajaxUpdate.yiiGridView", "#'.$this->grid->id.'", function() {'.$script.'});
            ');
            $this->_isScriptRendered = true;
        }
    }

   /**
    * Yii yet does not support custom js events in widgets.
    * So we need to invoke it manually to ensure update of editables on grid ajax update.
    *
    * issue in Yii github: https://github.com/yiisoft/yii/issues/1313
    *
    */
    protected function attachAjaxUpdateEvent()
    {
        $trigger = '$("#"+id).trigger("ajaxUpdate.yiiGridView");';

        //check if trigger already inserted by another column
        if(strpos($this->grid->afterAjaxUpdate, $trigger) !== false) return;

        //inserting trigger
        if(strlen($this->grid->afterAjaxUpdate)) {
            $orig = $this->grid->afterAjaxUpdate;
            if(strpos($orig, 'js:')===0) $orig = substr($orig,3);
            $orig = "\n($orig).apply(this, arguments);";
        } else {
            $orig = '';
        }
        $this->grid->afterAjaxUpdate = "js: function(id, data) {
            $trigger $orig
        }";
    }
}
