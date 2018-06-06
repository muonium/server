# Code of conduct

## Standards ([from](http://contributor-covenant.org/version/1/4/))

Examples of behavior that contributes to creating a positive environment include:

    Using welcoming and inclusive language
    Being respectful of differing viewpoints and experiences
    Gracefully accepting constructive criticism
    Focusing on what is best for the community
    Showing empathy towards other community members

Examples of unacceptable behavior by participants include:

    The use of sexualized language or imagery and unwelcome sexual attention or advances
    Trolling, insulting/derogatory comments, and personal or political attacks
    Public or private harassment
    Publishing others' private information, such as a physical or electronic address, without explicit permission
    Other conduct which could reasonably be considered inappropriate in a professional setting

# Our utilization of Git
- If you're coding a new feature, then create a new branch
- When the feature is coded, you can merge and delete the feature branch

## Issue
Please, do not open an issue if it is not relevant. Think about what you want to report, and check if it has been answered.

## Pull requests
Your code must be tested.

We do not care about the number of commits. **So, squashing is not useful.**

If something is wrong, we discuss in the pull request comments.

In fact : a feature <=> a branch

Only the team @muonium/server_commiters can merge to master.

### master branch
Your code must be tested before to be merged in the master branch.

# Coding style

## Global
- Use indentation
- The syntax must be light, flexible and simple. If someone needs, the code have to be easy to be modified.
- The name of a function/class/variable must be understandable without any comment.
- All functions, classes, variables names must be in English.
- Try to limit the SQL queries
- Verify data sent and use prepared statements for queries
- Useful "defines" can be found in index.php

## More precisely
- cloud.sql is the current database structure
![database structure](https://image.noelshack.com/fichiers/2017/43/4/1509050889-muidb.png)

- For the PHP side, we use a MVC architecture :
    - Controllers (application/controllers) : Filename must be the same as class name inside this file (one class per file).
    - Models (application/models) : Filename must be the same as class name inside this file and the same as table name in db.
        - One model = One table.

- MVC details
    - URL structure : http://[...]/[MVC root folder]/[Controller]/[Method]/[Param 1]/[Param 2].../?[query string]
        - Method and params are not necessary
        - If you do not specify a method, the method "DefaultAction" will be called if it exists.
    - Methods with the "Action" suffixe are specific methods which can be called by the URL, for exemple "addAction" in "folders" controller will be called with the URL "http://[...]/folders/add". Others methods can't be called by the URL.
    - All controllers extends library\MVC\Controller.php class which provides sessions management and useful vars like $_uid, $_token, $_jti
    - loadLanguage method gets the user's language json and store it in public static var "$txt".
    - All models extends library\MVC\Model.php class which gets the sql connection in protected static var "$_sql".
    - We use namespaces, PHP files must start with <?php namespace [...]; ([...] = path to the file's folder from MVC root folder)
        - For a example, for a controller : <?php namespace application\controllers;
        - Different use directives to create aliases which can be used :
            - use \library\MVC as l;
            - use \application\controllers as c;
            - use \application\models as m;
            - use \config as conf;
        - Do not forget to add "\" before other classes like Exception or PDO.
        - Example : Initializing files model with user id
        ```php
        $this->_modelFiles = new m\Files();
        $this->_modelFiles->id_owner = $this->_uid;
        ```
        or
        ```php
        $this->_modelFiles = new m\Files($this->_uid);
        ```
    - An example of constructor when the user must be logged :
    ```php
    function __construct() {
        parent::__construct([
            'mustBeLogged' => true
        ]);
    }
    ```
      - You can also check it inside methods thanks to isLogged() inherited from Controller class

    - Please check our controllers methods to know how to get and return JSON data.
    - **All strings must be in all json files.** Please check [translations repository](https://github.com/muonium/translations).
        - Example
        ```php
        echo self::$txt->Global->back;
        ```

- Naming convention
    - className, methodNameAction
        - methodNameAction refers to a specific method (look above "MVC details")
    - methodName, attributeName
    - var_name, database_column, model_attribute
        - It is better if attributes in models which refers to db columns have the same name and same case.
    - models used in controllers are defined for example like this : private $_modelUser;

## Comments
Comments must be written in English.
[Please, take this code as a reference](https://github.com/muonium/server/blob/master/application/controllers/session.php)

# PHP
## Version
We're using PHP version 7.0 and Muonium is compatible from PHP 5.6

# Support
- We do not support IE and Safari.
- We support only the recent web browsers.

# Directories
server/ : web application
nova/ : where the users datum are stored

# AUTHORS file
In your pull request, you can add your name to AUTHORS.md.
