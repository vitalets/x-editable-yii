<?php
/**
 * EditableField class file.
 *
 * @author Vitaliy Potapov <noginsk@rambler.ru>
 * @link https://github.com/vitalets/x-editable-yii
 * @copyright Copyright &copy; Vitaliy Potapov 2012
 * @version 1.3.0
 */

Yii::import('editable.Editable');

/**
 * EditableField widget makes editable single attribute of model.
 *
 * @package widgets
 */
class EditableField extends Editable
{
    /**
    * @var CActiveRecord ActiveRecord to be updated.
    */
    public $model = null;
    /**
    * @var string attribute name.
    */
    public $attribute = null;
   
    /**
    * @var instance of model that is created always:
    * E.g. if related model does not exist, it will be `newed` to be able tp get Attribute label, etc
    * for live update. 
    */
    private $staticModel = null;
    
    /**
    * initialization of widget
    *
    */
    public function init()
    {
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
        } else {
            $this->apply = true;
        }

        //attribute contains dot: related model, trying to resolve
        $explode = explode('.', $this->attribute);
        $len = count($explode);
        if($len > 1) {
            $this->attribute = $explode[$len-1];
            //try to resolve model instance  
            $model = $this->model;
            $resolved = true;
            for($i = 0; $i < $len-1; $i++) {
                $name = $explode[$i];
                if($model->$name instanceof CActiveRecord) {
                    $model = $model->$name;
                } else {
                    //related model not exist! Render text only.
                    $this->apply = false;
                    $resolved = false;
                    $this->text = $originalText;
                    break;
                }
            }
            
            if($resolved) {
                $this->staticModel = $model;
                $this->model = $model;
            } else {
                $relationName = $explode[$len-2];
                $className = $this->model->getActiveRelation($relationName)->className;
                $this->staticModel = new $className();
                $this->model = null;                
            }
        } else {
            $this->staticModel = $this->model;  
        }

        //for security reason only safe attributes can be editable (e.g. defined in rules of model)
        //just print text (see 'run' method)
        if (!$this->staticModel->isAttributeSafe($this->attribute)) {
            $this->apply = false;
            $this->text = $originalText;
        }

        /*
         try to detect type from metadata if not set
        */
        if ($this->type === null) {
            $this->type = 'text';
            if (array_key_exists($this->attribute, $this->staticModel->tableSchema->columns)) {
                $dbType = $this->staticModel->tableSchema->columns[$this->attribute]->dbType;
                if($dbType == 'date') {
                    $this->type = 'date';
                }
                if($dbType == 'datetime') {
                    $this->type = 'datetime';
                }
                if(stripos($dbType, 'text') !== false) {
                    $this->type = 'textarea';
                }
            }
        }

        //name
        if(empty($this->name)) {
            $this->name = $this->attribute;
        }
        
        //pk
        if($this->model && !$this->model->isNewRecord) {
            $this->pk = $this->model->primaryKey;
        }        
        
        parent::init();        
        
        /*
         If text not defined, generate it from model attribute for types except lists ('select', 'checklist' etc)
         For lists keep it empty to apply autotext.
         $this->_prepareToAutotext calculated in parent class Editable.php
        */
        if (!strlen($this->text) && !$this->_prepareToAutotext) {
            $this->text = $originalText;
        }
        
        //set value directly for autotext generation
        if($this->model && $this->_prepareToAutotext) {
            $this->value = $this->model->getAttribute($this->attribute); 
        }
        
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
            $this->title = $title . ' ' . $this->staticModel->getAttributeLabel($this->attribute);
        } else {
            $this->title = strtr($this->title, array('{label}' => $this->staticModel->getAttributeLabel($this->attribute)));
        }
        
        //scenario
        if($this->model && !isset($this->params['scenario'])) {
            $this->params['scenario'] = $this->model->getScenario(); 
        }        
    }

    public function getSelector()
    {
        return str_replace('\\', '_', get_class($this->staticModel)).'_'.parent::getSelector();
    }
}
