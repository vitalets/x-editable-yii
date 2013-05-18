<?php
/**
 * EditableField class file.
 *
 * @author Vitaliy Potapov <noginsk@rambler.ru>
 * @link https://github.com/vitalets/x-editable-yii
 * @copyright Copyright &copy; Vitaliy Potapov 2012
 * @version 1.1.0
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

        //name
        $this->name = $this->attribute;
        
        //pk
        if(!$this->model->isNewRecord) {
            $this->pk = $this->model->primaryKey;
        }

        parent::init();
        
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

        /*
         If text not defined, generate it from model attribute for types except lists ('select', 'checklist' etc)
         For lists keep it empty to apply autotext.
         $this->_prepareToAutotext calculated in parent class Editable.php
        */
        if (!strlen($this->text) && !$this->_prepareToAutotext) {
            $this->text = $originalText;
        }
        
        //set value directly for autotext generation
        if($this->_prepareToAutotext) {
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
            $this->title = $title . ' ' . $this->model->getAttributeLabel($this->attribute);
        } else {
            $this->title = strtr($this->title, array('{label}' => $this->model->getAttributeLabel($this->attribute)));
        }        
    }

    public function getSelector()
    {
        return str_replace('\\', '_', get_class($this->model)).'_'.parent::getSelector();
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
