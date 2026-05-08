<?php

$host = "sql107.infinityfree.com";
$db   = "if0_41856900_environet_db";
$user = "if0_41856900";
$pass = "9QpLpcTgRAH";

  $pdo = new PDO(
    "mysql:host=sql107.infinityfree.com;port=3306;dbname=if0_41856900_environet_db;charset=utf8",
    "if0_41856900",
    "9QpLpcTgRAH"
);
try {
  

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8",
        $user,
        $pass
    );

    echo "DATABASE OK";

} catch(PDOException $e) {

    echo "ERROR : " . $e->getMessage();
}
?>
