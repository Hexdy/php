<?php
include_once "Data/database_model.php";
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

$authorization = __FILE__;
function datatype($var, $type)
{
    return gettype($var) === $type;
}

function session_close(QueryCall $ctl, $token)
{
    if (!isset($ctl, $token)) {
        return "400, BAD REQUEST: Wrong data type";
    } elseif (!datatype($token, "string")) {
        return "400, BAD REQUEST: Wrong data type";
    } elseif (strlen($token) >= 15 || strlen($token) < 8) {
        return "400, BAD REQUEST: Wrong data type";
    }


    $ctl->delete("inicia", ["$token"], ["sesion_token"])->call();
    $ctl->update("sesion", [$token, "Finalizada"], ["token"], ["token", "estado"])->call();
    return [False];
}

function token_generator()
{
    $allowedCharacters = '0123456789abcdefghijklmnñopqrstuvwxyzABCDEFGHIJKLMNÑOPQRSTUVWXYZ';
    $textLength = rand(8, 14);
    $randomText = '';

    for ($i = 0; $i < $textLength; $i++) {
        $randomText .= $allowedCharacters[rand(0, strlen($allowedCharacters) - 1)];
    }

    return $randomText;
}

function session(QueryCall $ctl, $token)
{
    if (!isset($ctl, $token)) {
        return "400, BAD REQUEST: Wrong data type";
    } elseif (!is_string($token)) {
        return "400, BAD REQUEST: Wrong data type";
    } elseif (strlen($token) >= 15 || strlen($token) < 8) {
        return "400, BAD REQUEST: Wrong data type";
    }

    $is_session_active = $ctl->select("sesion", ["estado"], [$token], ["token"])->call();

    if ($is_session_active[0] === "Activa") {
        $query = "SELECT sesion.final_de_sesion,
                sesion.estado,
                web.primer_nombre, 
                web.primer_apellido
                FROM inicia
                JOIN sesion ON inicia.sesion_token = sesion.token
                JOIN web ON inicia.cliente_id = web.cliente_id
                WHERE inicia.sesion_token = '$token';
            ";

        $response = $ctl->setQuery($query)->call();
        if (!is_array($response) || count($response) === 0 || is_string($response)) {
            return "404, NOT FOUND: The given TOKEN doesn't exist";
        } else {

            $actualDate = date('Y-m-d H:i:s');

            $dbDate = $response[0];

            if ($actualDate <= $dbDate) {
                $newDate = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' +15 minutes'));

                $ctl->update("sesion", [$token, $actualDate, $newDate], ["token"], ["token", "ultima_sesion", "final_de_sesion"])->call();
                return [True, $response[2], $response[3]];
            } else if ($actualDate > $dbDate) {
                return session_close($ctl, $token);
            }
        }
    } else {
        return [false];
    }
}


function login(QueryCall $ctl, $mail, $passwd, string $token = "")
{
    $values = func_get_args();

    unset($values[0]);
    unset($values[3]);
    $length_verificator = true;

    foreach ($values as $var) {
        $length_verificator = $length_verificator && (strlen(strval($var)) <= 30) && (strlen(strval($var)) >= 2);
    }

    $type_verificator = true;

    foreach ($values as $var) {
        $type_verificator = $type_verificator && is_string($var);
    }

    if (!isset($ctl, $mail, $passwd)) {
        return "400 Bad Request: Missing data";
    } elseif (!$type_verificator) {
        return "400 Bad Request: Wrong data type";
    } elseif (!$length_verificator) {
        return "400 Bad Request: Wrong data length";
    }

    if (!empty($token)) {
        session_close($ctl, $token);
    }
    $new_token = token_generator();

    $query = "SELECT cliente.id, web.primer_nombre, web.primer_apellido, cliente.autorizacion
    FROM cliente 
    JOIN web  ON cliente.id = web.cliente_id
    WHERE cliente.email = '$mail' AND cliente.contrasenia = '$passwd'";

    $response = $ctl->setQuery($query)->call();

    if ($response && count($response) === 4) {
        $id = $response[0];
        $auth = $response[3];

        if ($auth === "Autorizado") {
            $actual_session = date('Y-m-d H:i:s');

            $query = "UPDATE sesion
        JOIN inicia ON sesion.token = inicia.sesion_token
        SET sesion.estado = 'Finalizada'
        WHERE inicia.cliente_id = $response[0];";
            $ctl->setQuery($query)->call();

            $query = "DELETE
        FROM inicia WHERE inicia.cliente_id = $response[0]";
            $ctl->setQuery($query)->call();

            $last_session = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' +15 minutes'));

            $ctl->insert("sesion", [$new_token, $actual_session, $last_session, $last_session, "Activa"],  ["token", "inicio_de_sesion", "ultima_sesion", "final_de_sesion", "estado"])->call();
            $ctl->insert("inicia", [$new_token, $id],  ["sesion_token", "cliente_id"])->call();
            return [True, $new_token, $response[1], $response[2]];
        } else {
            return "403, FORBIDDEN: You are not allowed to enter the system";
        }
    } else {
        return "404, NOT FOUND: The user wasn't found";
    }
}


function register_web_first(QueryCall $ctl, $first_name, $first_surname, $doc_type, $doc, $mail, $password)
{
    $values = func_get_args();

    unset($values[0]);

    $length_verificator = True;

    $maximum = [30, 30, 20, 11, 40, 40];

    foreach ($values as $index => $var) {
        $length_verificator = $length_verificator && (strlen(strval($var)) <= $maximum[$index - 1]);
    }

    $length_verificator = strlen($password) > 6 && strlen($mail) > 6 && $length_verificator;


    $type_verificator = True;

    foreach ($values as $var) {
        $type_verificator = $type_verificator && is_string($var);
    }

    if (!isset($ctl, $first_name, $first_surname, $doc_type, $doc, $mail, $password)) {
        return "400, BAD REQUEST: Missing data";
    } elseif (!$type_verificator) {
        return "400, BAD REQUEST: Wrong data type";
    } elseif (!ctype_digit($values[4])) {
        return "400, BAD REQUEST: Wrong data type";
    } elseif (!$length_verificator) {
        return "400, BAD REQUEST: Wrong data type";
    }

    $existence_verificator_doc = empty($ctl->select("web", ["cliente_id"], [$doc_type, $doc], ["tipo", "numero"])->call());
    $existence_verificator_mail = empty($ctl->select("cliente", ["email"], [$mail], ["email"])->call());

    if (!$existence_verificator_doc) {
        return "409, CONFLICT: This client already exists";
    } else if (!$existence_verificator_mail) {
        return "409, CONFLICT: This Email is already in use";
    }

    if ($ctl->insert("cliente", [$mail, $password, "En espera"], ["email", "contrasenia", "autorizacion"])->call() === ["OK", 200]) {
        $id = $ctl->select("cliente", ["id"], [$mail], ["email"])->call();
        $ctl->insert("web", [$id[0], $first_name, $first_surname, $doc_type, $doc], ["cliente_id", "primer_nombre", "primer_apellido", "tipo", "numero"])->call();
        login($ctl, $mail, $password, "");
        return ["OK", 200];
    }
}

function register_web_second(QueryCall $ctl, $token, $second_name, $second_surname, $street, $neighborhood, $city)
{
    $values = func_get_args();

    unset($values[0]);

    $length_verificator = True;

    foreach ($values as $var) {
        $length_verificator = $length_verificator && (strlen(strval($var)) <= 30) && (strlen(strval($var)) >= 2);
    }

    $type_verificator = True;

    foreach ($values as $var) {
        $type_verificator = $type_verificator && datatype($var, "string");
    }

    if (!isset($ctl, $second_name, $second_surname, $street, $neighborhood, $city)) {
        return "400, BAD REQUEST: Missing data";
    } elseif (!$type_verificator) {
        return "400, BAD REQUEST: Wrong data type";
    } elseif (!$length_verificator) {
        return "400, BAD REQUEST: Wrong data type";
    }

    $user = session($ctl, $token);
    $id = $ctl->select("inicia", ["cliente_id"], [$token], ["sesion_token"])->call()[0];
    if ($user[0]) {
        return $ctl->update("web", [$id, $second_name, $second_surname], ["cliente_id"], ["cliente_id", "segundo_nombre", "segundo_apellido"])->call();
    } else {
        return "401, UNAUTHORIZED: The session expired";
    }
}


function show_shop(QueryCall $ctl, string $token)
{
    $favorites = [];
    if (!isset($ctl)) {
        return "400 Bad Request: Missing data";
    } elseif (!empty($token)) { //Si el token esta entre los valores 8 y 15, y no está vacío
        if ((strlen($token) < 8 || strlen($token) >= 15)) {
            return "400, BAD REQUEST: Wrong data type";
        } else {

            $is_session = session($ctl, $token);
            if (is_array($is_session)) { //Si is_session es un array y tiene un valor en 0
                if ($is_session[0]) {
                    $id = $ctl->setQuery("SELECT web.cliente_id
                    FROM inicia
                    JOIN cliente ON inicia.cliente_id = cliente.id
                    JOIN web ON cliente.id = web.cliente_id
                    WHERE inicia.sesion_token = '0WeGy5MYIpAryN'")->call();
                    if ($id) {
                        $favorites = $ctl->select("favorito", ["menu_id"], [$id[0]], ["web_id"])->call();
                    }
                }
            }
        }
    }
    $menus = $ctl->setQuery("SELECT * FROM menu")->call();
    $result = [];
    foreach ($menus as $menu) {
        $id = $ctl->select("conforma", ["vianda_id"], [$menu[0]], ["menu_id"])->call()[0];
        $food = $ctl->setQuery("SELECT vianda.nombre, vianda_dieta.dieta
        FROM vianda
        JOIN vianda_dieta ON vianda.id = vianda_dieta.vianda_id
        WHERE vianda.id = '$id'")->call();
        array_push($menu, $food);
        array_push($result, $menu);
    }
    foreach ($menus as $menu) {
        if (!is_array($menu) || count($menu) !== 6) {
            print_r($menu);
            return "400 Bad Request: El formato del menú no es válido";
        }

        if (!is_array($result[6]) && count($result[6]) < 2) {

            return "400 Bad Request: El formato del menú no es válido 2";
        }
    }
    return [$result, $favorites];
    ## menues = [[menu=>id, nombre, calorias, frecuencia,descripcion, precio, [nombre_vianda, dieta_vianda]], [[]]]

}