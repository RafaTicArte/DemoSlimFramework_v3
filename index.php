<?php
/**
 * index.php
 *
 * Copyright 2016 TicArte <rafa@ticarte.com>
 *
 * Demo sobre el funcionamiento de SlimFramework v3
 *
 **/

/**
 * Función de conexión a la base de datos
 **/
function connectionDB() {
      /**
       * Conexión a base de datos MySQL
       **/
      // Datos
      $dbhost = "localhost";
      $dbuser = "root";
      $dbpass = "";
      $dbname = "appcontecimientos";
      // Inicia conexión a la base de datos
      $dbcon = new PDO("mysql:host=$dbhost; dbname=$dbname", $dbuser, $dbpass);
      if ($dbcon != null) {
         // Activa las excepciones en el controlador PDO
         $dbcon->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         // Fuerza la codificación de caracteres a UTF8
         $dbcon->exec("SET NAMES 'utf8'");
      }

      /**
       * Conexión a base de datos SQLite
       **/
      //$dbcon = new PDO("sqlite:appcontecimientos.sqlite");

      // Devuelve la conexión
      return $dbcon;
}

// Registra la librería Slim descargada con Composer
require 'vendor/autoload.php';

// Crea la aplicación con el servidor REST
$app = new Slim\App();

/**
 * Operación GET de recuperación de un recurso mediante su identificador
 **/
$app->get('/acontecimiento[/[{param_id}]]', function ($request, $response, $args) {
   // Comprueba los parámetros
   if (empty($args['param_id'])){
         $output = '{"error":-14, "message":"Parámetros incorrectos"}';
   } else {
      // Crea los parámetros
      $param_id = intval($args['param_id']);

      // Sentencias SQL
      $sql_acontecimiento = "SELECT * FROM acontecimientos WHERE id=:bind_id";
      $sql_eventos = "SELECT * FROM eventos WHERE id_acontecimiento=:bind_id";

      try {
         // Conecta con la base de datos
         $db = connectionDB();

         if ($db != null) {
            // Prepara y ejecuta la sentencia
            $stmt_acontecimiento = $db->prepare($sql_acontecimiento);
            $stmt_acontecimiento->bindParam(":bind_id", $param_id, PDO::PARAM_INT);
            $stmt_acontecimiento->execute();

            // Obtiene un array asociativo con un registro
            $record_acontecimiento = $stmt_acontecimiento->fetch(PDO::FETCH_ASSOC);

            if ($record_acontecimiento != false) {
               // Elimina los valores vacíos del registro
               $record_acontecimiento = array_filter($record_acontecimiento);

               $output = '{"acontecimiento":';

               // Convierte el array a formato JSON con caracteres Unicode y modo tabulado
               // Deshabilitar JSON_PRETTY_PRINT con el servidor REST en producción
               $output .= json_encode($record_acontecimiento, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

               // Prepara y ejecuta la sentencia
               $stmt_eventos = $db->prepare($sql_eventos);
               $stmt_eventos->bindParam(":bind_id", $param_id, PDO::PARAM_INT);
               $stmt_eventos->execute();

               // Obtiene uno a uno los registros para eliminar los valores vacíos en ellos
               $record_eventos = array();
               while ($record = $stmt_eventos->fetch(PDO::FETCH_ASSOC))
                  array_push($record_eventos, array_filter($record));

               if (sizeof($record_eventos) != 0) {
                  $output .= ',"eventos":';

                  // Convierte el array a formato JSON con caracteres Unicode y modo tabulado
                  // Deshabilitar JSON_PRETTY_PRINT con el servidor REST en producción
                  $output .= json_encode($record_eventos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
               }

               $output .= '}';
            } else {
               $output = '{"error": -11, "message": "El acontecimiento no existe"}';
            }

            // Cierra la conexión con la base de datos
            $db = null;
         }
      } catch (PDOException $e) {
         $output = '{"error": -9, "message": "Excepción en la base de datos: ' . $e->getMessage() . '"}';
      }
   }

   return $response
      ->withHeader('Content-type', 'application/json; charset=UTF-8')
      ->write($output);
});

/**
 * Operación GET de recuperación de recursos mediante palabras
 **/
$app->get('/buscar/nombre[/[{param_words}]]', function ($request, $response, $args) {
   // Comprueba los parámetros
   if (empty($args['param_words'])){
      $output = '{"error":-14, "message":"Parámetros incorrectos"}';
   } else {
      // Comprueba el parámetro de entrada y lo separa en palabras
      $array_words = explode(' ', $args['param_words']);

      if (sizeof($array_words) != 0) {
         // Crea la sentencia SQL añadiendo la condición por cada palabra buscada
         // A la palabra se le añade el carácter '%' para la búsqueda
         // Se elimina de la sentencia el último 'AND' para evitar errores de sintaxis
         $sql_busqueda = "SELECT id, nombre FROM acontecimientos WHERE";
         foreach ($array_words as $clave => $valor) {
            $array_words[$clave] = '%' . $valor . '%';
            $sql_busqueda .= " nombre LIKE ? AND";
         }
         $sql_busqueda = substr($sql_busqueda, 0, -4);

         try {
            // Conecta con la base de datos
            $db = connectionDB();

            if ($db != null) {
               // Prepara y ejecuta la sentencia
               $stmt_busqueda = $db->prepare($sql_busqueda);
               $stmt_busqueda->execute($array_words);

               // Obtiene un array asociativo con los registros
               $records_busqueda = $stmt_busqueda->fetchAll(PDO::FETCH_ASSOC);

               if ($records_busqueda != false) {
                  $output = '{"acontecimientos":';

                  // Convierte el array a formato JSON con caracteres Unicode y modo tabulado
                  // Deshabilitar JSON_PRETTY_PRINT con el servidor REST en producción
                  $output .= json_encode($records_busqueda, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                  $output .= '}';
               } else {
                  $output = '{"error": -12, "message": "No se han encontrado acontecimientos"}';
               }

               // Cierra la conexión con la base de datos
               $db = null;
            }
         } catch (PDOException $e) {
            $output = '{"error": -9, "message": "Excepción en la base de datos: ' . $e->getMessage() . '"}';
         }
      } else {
         $output = '{“error”:-13, “message”:”Parámetros de búsqueda incorrectos”}';
      }
   }

   return $response
      ->withHeader('Content-type', 'application/json; charset=UTF-8')
      ->write($output);
});

/**
 * Operación POST de inserción de un recurso
 * Formato JSON:
 *    { "acontecimiento": {
 *        "nombre": "Jornadas TicArte",
 *        "email": "info@ticarte.com"
 *       }
 *    }
 **/
$app->post('/acontecimiento', function ($request, $response, $args) {
   // Obtiene el body de la petición recibida
   $request_body = $request->getBody();

   // Transforma el contenido JSON del body en un array
   $acontecimiento = json_decode($request_body, true, 10);

   // Comprueba los errores en el contenido JSON
   if (json_last_error() != JSON_ERROR_NONE) {
      $output = '{"error":-21, "message": "Contenido JSON con errores"}';
   } else {
      // Comprueba los valores del contenido JSON
      $acontecimiento['acontecimiento']['nombre'] = (!empty($acontecimiento['acontecimiento']['nombre'])) ? $acontecimiento['acontecimiento']['nombre'] : '';
      $acontecimiento['acontecimiento']['email'] = (!empty($acontecimiento['acontecimiento']['email'])) ? $acontecimiento['acontecimiento']['email'] : '';

      // Sentencias SQL
      $sql_insert = "INSERT INTO acontecimientos (nombre, email) VALUES (:bind_nombre, :bind_email)";

      try {
         // Conecta con la base de datos
         $db = connectionDB();

         if ($db != null){
            // Prepara y ejecuta de la sentencia
            $stmt_insert = $db->prepare($sql_insert);
            $stmt_insert->bindParam(":bind_nombre", $acontecimiento['acontecimiento']['nombre'], PDO::PARAM_STR);
            $stmt_insert->bindParam(":bind_email", $acontecimiento['acontecimiento']['email'], PDO::PARAM_STR);
            $stmt_insert->execute();

            $output = '{"error": 1, "message": "Acontecimiento insertado correctamente con el id '.$db->lastInsertId().'"}';

            // Cierra la conexión con la base de datos
            $db = null;
         }
      } catch(PDOException $e) {
         $output = '{"error": -9, "message": "Excepción en la base de datos: '.$e->getMessage().'"}';
      }
   }

   return $response
      ->withHeader('Content-type', 'application/json; charset=UTF-8')
      ->write($output);
});

// Inicia la aplicación con el servidor REST
$app->run();

?>