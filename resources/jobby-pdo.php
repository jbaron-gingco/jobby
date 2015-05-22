<?php

//
// Add this line to your crontab file:
//
// * * * * * cd /path/to/project && php jobby-pdo.php 1>> /dev/null 2>&1
//

require(__DIR__ . '/../vendor/autoload.php');

$dbhHost = '';
$dbhUser = '';
$dbhPassword = '';
$dbhDatabaseName = '';
$dbhJobbiesTableName = 'jobbies';

#$dbh = new PDO('mysql:host=' . $dbhHost . ';dbname=' . $dbhDatabaseName, $dbhUser, $dbhPassword);
#$dbh = new PDO('sqlite:messaging.sqlite3');
$dbh = new PDO('sqlite::memory:');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/*
 * Setup a test-fixture.
 */

if ($dbhDatabaseName) {
    $dbh->exec("CREATE DATABASE IF NOT EXISTS `$dbhDatabaseName`; USE $dbhDatabaseName");
}
$dbh->exec("
CREATE TABLE `$dbhJobbiesTableName`
(`name` VARCHAR(255) NOT NULL ,
 `command` TEXT NOT NULL ,
 `schedule` VARCHAR(255) NOT NULL ,
 `mailer` VARCHAR(255) NULL DEFAULT 'sendmail' ,
 `maxRuntime` INT UNSIGNED NULL ,
 `smtpHost` VARCHAR(255) NULL ,
 `smtpPort` SMALLINT UNSIGNED NULL ,
 `smtpUsername` VARCHAR(255) NULL ,
 `smtpPassword` VARCHAR(255) NULL ,
 `smtpSender` VARCHAR(255) NULL DEFAULT 'jobby@localhost' ,
 `smtpSenderName` VARCHAR(255) NULL DEFAULT 'Jobby' ,
 `smtpSecurity` VARCHAR(20) NULL ,
 `runAs` VARCHAR(255) NULL ,
 `environment` TEXT NULL ,
 `runOnHost` VARCHAR(255) NULL ,
 `output` VARCHAR(255) NULL ,
 `dateFormat` VARCHAR(100) NULL DEFAULT 'Y-m-d H:i:s' ,
 `enabled` BOOLEAN NULL DEFAULT TRUE ,
 `haltDir` VARCHAR(255) NULL , `debug` BOOLEAN NULL DEFAULT FALSE ,
 PRIMARY KEY (`name`)
)
");

$insertJob = $dbh->prepare("
INSERT INTO `$dbhJobbiesTableName`
 (`name`,`command`,`schedule`,`runOnHost`,`output`)
 VALUES
 (:name,:command,:schedule,:runOnHost,:output)
");
// First demo-job.
$insertJob->execute(
    array('CommandExample', 'date', '* * * * *', php_uname('n'), 'logs/command-pdo.log')
);
// Second demo-job.
$secondJobFn = function() {
    echo "I'm a function (" . date('Y-m-d H:i:s') .")!" . PHP_EOL;
    return true;
};
$secondJobFnSerializable = new \SuperClosure\SerializableClosure($secondJobFn);
$secondJobFnSerialized = serialize($secondJobFnSerializable);
$insertJob->execute(
    array('ClosureExample', $secondJobFnSerialized, '* * * * *', php_uname('n'), 'logs/closure-pdo.log')
);

/*
 * Fetch all jobbies from database and run them.
 */

$jobbiesStmt = $dbh->query("SELECT * FROM `$dbhJobbiesTableName`");
$jobbies = $jobbiesStmt->fetchAll(PDO::FETCH_ASSOC);

$jobby = new \Jobby\Jobby();

foreach ($jobbies as $job) {
    $jobName = $job['name'];
    unset($job['name']);

    $commandUnserialized = @unserialize($job['command']);
    if (false !== $commandUnserialized) {
        $job['command'] = $commandUnserialized;
    }
    $jobby->add($jobName, $job);
}

$jobby->run();
