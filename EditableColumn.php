<?php
/**
 * EditableColumn class file.
 *
 * @author Vitaliy Potapov <noginsk@rambler.ru>
 * @link https://github.com/vitalets/x-editable-yii
 * @copyright Copyright &copy; Vitaliy Potapov 2012
 * @version 1.3.0
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

    public function init()
    {
        if (!$this->name) {
            throw new CException('You should provide name for EditableColumn');
        }

        parent::init();

        //need to attach ajaxUpdate handler to refresh editables on pagination and sort
        Editable::attachAjaxUpdateEvent($this->grid);
    }
   
    
    protected function renderDataCellContent($row, $data)
    {
        $isModel = $data instanceOf CModel;
        
        if($isModel) {
            $widgetClass = 'EditableField';
            $options = array(
                'model'        => $data,
                'attribute'    => empty($this->editable['attribute']) ? $this->name : $this->editable['attribute'],
            );
            
            //flag to pass `text` option into widget
            $passText = strlen($this->value);     
        } else {
            $widgetClass = 'Editable';
            $options = array(
                'pk'           => $data[$this->grid->dataProvider->keyField],
                'name'         => empty($this->editable['name']) ? $this->name : $this->editable['name'],
            );
            
            $passText = true;
            //if autotext will be applied, do not pass text param
            if(!strlen($this->value) && Editable::isAutotext($this->editable, isset($this->editable['type']) ? $this->editable['type'] : '')) {
               $options['value'] = $data[$this->name]; 
               $passText = false;
            } 
        }
        
        //for live update
        $options['liveTarget'] = $this->grid->id;
        
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

        // add additional data for source link requests
        if (isset($options['additional_data']) && is_array($options['additional_data'])) {
            foreach($options['additional_data'] as $data_key => $data_value_expression)
            {
                if(is_string($data_value_expression))
                    $options['additional_data'][$data_key] = $this->evaluateExpression($data_value_expression, array('data'=>$data, 'row'=>$row));
            }
        }           
        
        $this->grid->controller->widget($widgetClass, $options);
    }
    
    /*
    Require this overwrite to show bootstrap sort icons
    */
    protected function renderHeaderCellContent()
    {
        if(yii::app()->editable->form != EditableConfig::FORM_BOOTSTRAP) {
            parent::renderHeaderCellContent();
            return;
        }
        
        if ($this->grid->enableSorting && $this->sortable && $this->name !== null)
        {
            $sort = $this->grid->dataProvider->getSort();
            $label = isset($this->header) ? $this->header : $sort->resolveLabel($this->name);

            if ($sort->resolveAttribute($this->name) !== false)
                $label .= '<span class="caret"></span>';

            echo $sort->link($this->name, $label, array('class'=>'sort-link'));
        }
        else
        {
            if ($this->name !== null && $this->header === null)
            {
                if ($this->grid->dataProvider instanceof CActiveDataProvider)
                    echo CHtml::encode($this->grid->dataProvider->model->getAttributeLabel($this->name));
                else
                    echo CHtml::encode($this->name);
            }
            else
                parent::renderHeaderCellContent();
        }
    } 
    
    /*
    Require this overwrite to show bootstrap filter field
    */    
    public function renderFilterCell()
    {
        if(yii::app()->editable->form != EditableConfig::FORM_BOOTSTRAP) {
            parent::renderFilterCell();
            return;
        }
                
        echo '<td><div class="filter-container">';
        $this->renderFilterCellContent();
        echo '</div></td>';
    }       
}
