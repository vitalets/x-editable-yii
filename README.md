X-editable for Yii
======================

Bundle of Yii widgets and server-side component for creating editable elements using [X-editable](http://vitalets.github.com/x-editable) library.
This extension comes instead of previous [yii-bootstrap-editable](http://www.yiiframework.com/extension/yii-bootstrap-editable) that was upgraded.
Main changes are:

* support of several libraries: Twitter Bootstrap, jQuery UI and pure jQuery. Now it works with Yii out of box
* popup and inline modes. You can toggle it from config without changing code
* update of related models

It contains 3 widgets to be used in _views_:

* [EditableField](?r=site/widgets#EditableField) - outputs single attribute as editable element
* [EditableDetailView](?r=site/widgets#EditableField) - outputs whole model as editable name-value pairs
* [EditableColumn](?r=site/widgets#EditableField) - makes editable one column in GridView

and 2 components:

* [EditableConfig](?r=site/components#EditableConfig) - used in config file to setup extension
* [EditableSaver](?r=site/widgets#EditableSaver) - used in controller actions to update records

##Demo & Documentation
Please see [widgets section](?r=site/widgets)  

##Requirements
Requirements depend on core library you want to use:

* [Bootstrap](http://twitter.github.com/bootstrap)  
   Twitter Bootstrap 2+, Yii 1.1+. You can use [Yii-bootstrap](http://www.yiiframework.com/extension/bootstrap) extension (2.0+) **or** include bootstrap manually
* [jQuery UI](http://jqueryui.com)
    * popup: Yii 1.1.13+ (as requires jQuery UI 1.9 for tooltip)    
    * inline: Yii 1.1+
* [jQuery](http://jquery.com)  
    Yii 1.1+ 

###Setup

1. Download and unzip to `protected/extensions/x-editable`
2. If using with **Bootstrap**, [install Yii-bootstrap](http://www.cniska.net/yii-bootstrap/setup.html) or include Bootstrap js and css manually:  

        Yii::app()->clientScript->registerCssFile(Yii::app()->baseUrl.'/js/bootstrap/css/bootstrap.min.css');  
        Yii::app()->clientScript->registerScriptFile(Yii::app()->baseUrl.'/js/bootstrap/js/bootstrap.min.js');

3. Modify your config:
        
        //assume you unzipped extension under protected/extensions/x-editable
        Yii::setPathOfAlias('editable', dirname(__FILE__).'/../extensions/x-editable');

        return array(
            ...
            'import'=>array(
                ...
               'editable.*' //easy include of editable classes
            ),            
            
            //application components
            'components'=>array(
                ...
                //X-editable config
                'editable' => array(
                    'class'     => 'editable.EditableConfig',
                    'form'      => 'bootstrap',        //form style: 'bootstrap', 'jqueryui', 'plain' 
                    'mode'      => 'popup',            //mode: 'popup' or 'inline'  
                    'defaults'  => array(              //default settings for all editable elements
                       'emptytext' => 'Click to edit'
                    )
                ),        
            )
        );
        
That's it!  
Now you can edit *views* and *controller* to have editable elements. Please refer to [widgets section](?r=site/widgets).

