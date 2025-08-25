<?php

$host = "localhost";
$user = "root";
$password = "aluno";
$db = "sistema_cadastro";
$conn = new Mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["erro" => "Falha na conexão"], JSON_UNESCAPED_UNICODE);
    exit();
}

?>