<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require 'db.php'; // sua conexão com banco
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Função para validar senha
function validarSenha($senha) {
    $erros = [];

    if (strlen($senha) < 8) {
        $erros[] = "A senha deve ter pelo menos 8 caracteres.";
    }
    if (!preg_match('/[A-Z]/', $senha)) {
        $erros[] = "A senha deve conter pelo menos uma letra maiúscula.";
    }
    if (!preg_match('/[a-z]/', $senha)) {
        $erros[] = "A senha deve conter pelo menos uma letra minúscula.";
    }
    if (!preg_match('/\d/', $senha)) {
        $erros[] = "A senha deve conter pelo menos um número.";
    }
    if (!preg_match('/[\W_]/', $senha)) {
        $erros[] = "A senha deve conter pelo menos um caractere especial.";
    }

    return $erros;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['erro' => 'JSON inválido ou não enviado']);
        exit();
    }

    // Verifica campos obrigatórios
    if (
        !isset($data['nome'], $data['email'], $data['senha'], $data['telefone'], 
               $data['endereco'], $data['estado'], $data['data_nascimento'])
    ) {
        http_response_code(400);
        echo json_encode(['erro' => 'Todos os campos são obrigatórios']);
        exit();
    }

    $nome = $conn->real_escape_string($data['nome']);
    $email = $conn->real_escape_string($data['email']);
    $senhaRaw = $data['senha'];
    $telefone = $conn->real_escape_string($data['telefone']);
    $endereco = $conn->real_escape_string($data['endereco']);
    $estado = $conn->real_escape_string($data['estado']);
    $data_nascimento = $conn->real_escape_string($data['data_nascimento']);

    // Validações básicas
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Email inválido']);
        exit();
    }

    if (!preg_match('/^\(?\d{2}\)?\s?\d{4,5}-?\d{4}$/', $telefone)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Telefone inválido. Use formato (11) 91234-5678 ou 11912345678']);
        exit();
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_nascimento)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Data de nascimento inválida. Use AAAA-MM-DD']);
        exit();
    }

    // Valida senha
    $errosSenha = validarSenha($senhaRaw);
    if (count($errosSenha) > 0) {
        http_response_code(400);
        echo json_encode(['erro' => implode(" ", $errosSenha)]);
        exit();
    }

    // Hash da senha
    $senha = password_hash($senhaRaw, PASSWORD_DEFAULT);

    // Verifica duplicidade
    $verificaEmail = $conn->query("SELECT id FROM usuarios WHERE email = '$email'");
    if ($verificaEmail->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'Email já cadastrado']);
        exit();
    }

    $verificaTelefone = $conn->query("SELECT id FROM usuarios WHERE telefone = '$telefone'");
    if ($verificaTelefone->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'Telefone já cadastrado']);
        exit();
    }

    // Gerar ID personalizado: USR0001, USR0002 ...
    $result = $conn->query("SELECT id FROM usuarios ORDER BY criado_em DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $ultimo = $result->fetch_assoc();
        $ultimo_id_num = (int) preg_replace('/\D/', '', $ultimo['id']);
        $novo_id_num = $ultimo_id_num + 1;
    } else {
        $novo_id_num = 1;
    }
    $novo_id = 'USR' . str_pad($novo_id_num, 4, '0', STR_PAD_LEFT);

    // Insere no banco
    $sql = "INSERT INTO usuarios (id, nome, email, senha, telefone, endereco, estado, data_nascimento) 
            VALUES ('$novo_id', '$nome', '$email', '$senha', '$telefone', '$endereco', '$estado', '$data_nascimento')";

    try {
        $conn->query($sql);
        http_response_code(201);
        echo json_encode(['mensagem' => 'Cliente cadastrado com sucesso', 'id' => $novo_id]);
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro interno no servidor']);
    }
    exit();
}

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = $conn->real_escape_string($_GET['id']);
        $result = $conn->query("SELECT id, nome, email, telefone, endereco, estado, data_nascimento, criado_em FROM usuarios WHERE id = '$id'");
        if ($result->num_rows > 0) {
            $usuario = $result->fetch_assoc();
            echo json_encode($usuario);
        } else {
            http_response_code(404);
            echo json_encode(['erro' => 'Usuário não encontrado']);
        }
    } else {
        // Listar todos
        $result = $conn->query("SELECT id, nome, email, telefone, endereco, estado, data_nascimento, criado_em FROM usuarios ORDER BY criado_em DESC");
        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        echo json_encode($usuarios);
    }
    exit();
}

if ($method === 'DELETE') {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID do usuário é obrigatório para deletar']);
        exit();
    }

    $id = $conn->real_escape_string($_GET['id']);

    $result = $conn->query("SELECT id FROM usuarios WHERE id = '$id'");
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['erro' => 'Usuario não encontrado']);
        exit();
    }

    try {
        $conn->query("DELETE FROM usuarios WHERE id = '$id'");
        echo json_encode(['mensagem' => "Usuario $id deletado com sucesso"]);
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro interno no servidor ao deletar usuário']);
    }
    exit();
}

http_response_code(405);
echo json_encode(['erro' => 'Método não suportado']);
exit();
?>