SQLAbstract.php
---
[![Build Status](https://travis-ci.org/unframed/SQLAbstract.php.svg)](https://travis-ci.org/unframed/SQLAbstract.php)

A practical SQL abstraction class with concrete conveniences for query building and execution.

Usefull to plugin and extend legacy PHP database applications.

Requirements
---
- provide practical conveniences to query tables and views
- with prefixed table names, guarded identifiers and custom placeholders
- support PHP 5.3, PDO and WPDB.

Synopsis
---
SQLAbstract is meant to safely query a single existing SQL database, eventually with prefixed table names.

So, let's assume a legacy 'task' table.

~~~sql
CREATE TABLE IF NOT EXISTS `prefix_task` (
    `task_id` INTEGER AUTOINCREMENT PRIMARY KEY,
    `task_name` VARCHAR(255) NOT NULL,
    `task_scheduled_for` INTEGER UNSIGNED NOT NULL,
    `task_completed_at` INTEGER UNSIGNED,
    `task_created_at` INTEGER UNSIGNED NOT NULL,
    `task_modified_at` INTEGER UNSIGNED NOT NULL,
    `task_deleted_at` INTEGER UNSIGNED,
    `task_description` MEDIUMTEXT
    );
~~~

Because you will find no `create` methods in SQLAbstract.

### Execute

Although nothing prevents you to execute arbitrary SQL statements.

For instance, to create a view:

~~~php
<?php

$sql = new SQLAbstractPDO($pdo, 'prefix_');
$sq->execute("

REPLACE VIEW ".$sql->prefixedIdentifier('task_view')." AS 
    SELECT 
        *,
        (task_scheduled_for > NOW()) 
        as task_due,
        (task_completed_at IS NULL OR task_completed_at < NOW()) 
        as task_completed
        (task_deleted_at NOT NULL) 
        as task_deleted
    FROM ".$sql->prefixedIdentifier('task').";

");

?>
~~~

Or fetch all tasks rows at once:

~~~php
<?php

$sql = new SQLAbstractPDO($pdo, 'prefix_');
$sq->fetchAll(
    "SELECT * FROM ".$sql->prefixedIdentifier('task_view')
    );

?>
~~~

You may use `execute` to insert, replace, update, select and count rows, but safe conveniences are provided.

### Insert

For instance, let's insert a `$task` array in the table `task` and update this task's identifier :

~~~php
<?php

$now = time();
$task = array(
    'task_name' => 'new task',
    'task_created_at' => $now,
    'task_scheduled_for' => $now + 3600,
    'task_modified_at' => $now
    );
$task['task_id'] = $sql->insert('task', $task);

?>
~~~

The following SQL statement will be executed, with safely bound parameters.

~~~sql
INSERT INTO `prefix_task` (
    `task_name`, 
    `task_created_at`, 
    `task_scheduled_for`, 
    `task_modified_at`, 
    ) VALUES (?, ?, ?, ?)
~~~

### Select and Replace

Let's now use safe options to select all tasks named 'new task', edit and then replace each task :

~~~php
<?php

foreach($sql->select('task', array(
    'filter' => array(
        'task_name' => 'new task'
        )
    )) as $task) {
    $task['task_modified_at'] = time();
    $sql->replace('task', $task);
}

?>
~~~

The following SQL statements will be executed, with safely bound parameters.

~~~sql
SELECT * FROM `prefix_task` WHERE 
    `task_name` = ? 
;
REPLACE INTO `prefix_task` (
    `task_name`, 
    `task_created_at`, 
    `task_scheduled_for`, 
    `task_modified_at`, 
    ) VALUES (?, ?, ?, ?)
;
~~~

Not very elegant in this case, but demonstrative of a common pattern.

### Update

As safe and more efficient way to update filtered rows is way simpler.

~~~php
<?php

$sql->update('task', array(
    'task_modified_at' => time()
), array(
    'filter' => array(
        'task_name' => 'new name'
    )
));

?>
~~~

Also, it executes a single SQL statement.

~~~sql
UPDATE `prefix_task` SET `task_modified_at` = ? WHERE `task_name` = ? 
~~~

### Delete

Deleting rows at once follows the same pattern, using the same options as `select`, `update` and `count`.

~~~php
<?php

$sql->delete('task', array(
    'filter' => array(
        'task_name' => 'new name'
    )
));

?>
~~~

Note the absence of litteral SQL, this code is free of SQL injection.

~~~sql
DELETE FROM `prefix_task` WHERE `task_name` = ? 
~~~

This is because we used safe options in all examples so far.

### Safe Options

The safe options to generate a WHERE clause are `filter` and `like`.

For instance, here is a bit more complex select statement. 

~~~php
<?php

$sql->select("task", array(
    "filter" => array(
        "task_id" => array(1,2,3),
        "task_deleted_at" => null
    ),
    "like" => array(
        "task_name" => "new%"
        "task_description" => "new%"
    )
));

?>
~~~

This implements the typical filter and search feature found in most database application and executes the following SQL :

~~~sql
SELECT * FROM `prefix_task` WHERE 
    `task_in` in (?, ?, ?) 
    AND task_delete_at = ?
    AND (
        `task_name` like ? 
        OR `task_description` like ?
        ) 
;
~~~

Given enough views these options can implement all selections.

### Unsafe Options

There are unsafe options though to use when filtering by keys and patterns does not apply and we don't have a view.

The `where` and `params` options allow to specify an SQL expression and a list of execution parameters.

Applications are expected to use the `identifier` and `placeholder` methods to build the expression.

~~~php
<?php

$now = time();
$sql->select("task", array(
    "where" => (
        $sql->identifier('task_delete_scheduled_for')
        ." > "
        .$sql->placeholder($now)
    ),
    "params" => array($now)
));

?>
~~~

Note that the SQL generated contains the literal `where` option.

~~~sql
SELECT * FROM `prefix_task` WHERE `task_scheduled_for` > ?
~~~

So, no user input should be passed as `where` option.

### Select Options

~~~json
{
    "columns": [],
    "order": [],
    "limit": 30,
    "offset": 0
}
~~~

...