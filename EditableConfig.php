<?php
/**
 * EditableConfig class file.
 *
 * @author Vitaliy Potapov <noginsk@rambler.ru>
 * @link https://github.com/vitalets/x-editable-yii
 * @copyright Copyright &copy; Vitaliy Potapov 2012
 * @version 1.3.1
 */

class EditableConfig extends CApplicationComponent
{
    const FORM_BOOTSTRAP = 'bootstrap';
    const FORM_JQUERYUI = 'jqueryui';
    const FORM_PLAIN = 'plain';

    const POPUP = 'popup';
    const INLINE = 'inline';

    /**
    * @var string editable form engine: bootstrap, jqueryui, plain
    */
    public $form = self::FORM_BOOTSTRAP;

    /**
    * @var string editable container type: popup or inline
    */
    public $mode = self::POPUP;

    /**
    * @var array defaults for editable configuration
    */
    public $defaults = array();

    /**
    * initializes editable component and sets defaults
    *
    */
    public function init()
    {
        parent::init();
        if(empty($this->defaults)) $this->defaults = array();
        //copy mode from first level config to defaults (for compability)
        if(empty($this->defaults['mode'])) $this->defaults['mode'] = $this->mode;
        $defaults = CJavaScript::encode($this->defaults);
        Yii::app()->getClientScript()->registerScript(
           'editable-defaults', 'if($.fn.editable) $.extend($.fn.editable.defaults, '.$defaults.');'
        );
    }
}
