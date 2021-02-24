<?php
$reload = false;

require_once('func.php');
require_once('database.php');
$GRAVITYDB = getGravityDBFilename();
$db = SQLite3_connect($GRAVITYDB, SQLITE3_OPEN_READWRITE);

$secretToken = "{your secret random generated code}";
if($_GET['token'] == $secretToken){

	switch($_GET['process']){
		case "get_groups":
			$query = $db->query('SELECT * FROM "group";');
        	$data = array();
        	while (($res = $query->fetchArray(SQLITE3_ASSOC)) !== false) {
        	    array_push($data, $res);
        	}
        	header('Content-type: application/json');
        	echo json_encode(array('data' => $data));
        break;
        case "unconfigured_clients":
            $QUERYDB = getQueriesDBFilename();
            $FTLdb = SQLite3_connect($QUERYDB);

            $query = $FTLdb->query('SELECT DISTINCT ip,network.name FROM network_addresses AS name LEFT JOIN network ON network.id = network_id ORDER BY ip ASC;');
            if (!$query) {
                throw new Exception('Error while querying FTL\'s database: ' . $db->lastErrorMsg());
            }

            // Loop over results
            $ips = array();
            while ($res = $query->fetchArray(SQLITE3_ASSOC)) {
                $ips[$res['ip']] = $res['name'] !== null ? $res['name'] : '';
            }
            $FTLdb->close();

            $query = $db->query('SELECT ip FROM client;');
            if (!$query) {
                throw new Exception('Error while querying gravity\'s database: ' . $db->lastErrorMsg());
            }

            // Loop over results, remove already configured clients
            while (($res = $query->fetchArray(SQLITE3_ASSOC)) !== false) {
                if (isset($ips[$res['ip']])) {
                    unset($ips[$res['ip']]);
                }
            }

            header('Content-type: application/json');
            echo json_encode($ips);

        break;
        case "add_client":
            $ips = explode(' ', trim($_POST['ip']));
            $total = count($ips);
            $added = 0;
            $stmt = $db->prepare('INSERT INTO client (ip,comment) VALUES (:ip,:comment)');
            if (!$stmt) {
                throw new Exception('While preparing statement: ' . $db->lastErrorMsg());
            }

            foreach ($ips as $ip) {
                if (!$stmt->bindValue(':ip', $ip, SQLITE3_TEXT)) {
                    throw new Exception('While binding ip: ' . $db->lastErrorMsg());
                }

                $comment = html_entity_decode($_POST['comment']);
                if (strlen($comment) === 0) {
                        // Store NULL in database for empty comments
                        $comment = null;
                }
                if (!$stmt->bindValue(':comment', $comment, SQLITE3_TEXT)) {
                    throw new Exception('While binding comment: <strong>' . $db->lastErrorMsg() . '</strong><br>'.
                    'Added ' . $added . " out of ". $total . " clients");
                }

                if (!$stmt->execute()) {
                    throw new Exception('While executing: <strong>' . $db->lastErrorMsg() . '</strong><br>'.
                    'Added ' . $added . " out of ". $total . " clients");
                }else{
                }
                $added++;
            }

            $db->query('BEGIN TRANSACTION;');
            $stmtClient = $db->prepare('SELECT DISTINCT id FROM client WHERE ip=:ip');
            if (!$stmtClient->bindValue(':ip', $ip, SQLITE3_TEXT)) {
                throw new Exception('While binding ip: ' . $db->lastErrorMsg());
            }
            $resultClient = $stmtClient->execute();
            if (!$resultClient) {
                throw new Exception('While executing network table statement: ' . $db->lastErrorMsg());
            }
            $id_result = $resultClient->fetchArray(SQLITE3_ASSOC);
            $clientID = $id_result['id'];

            $gid = $_POST['groupID'];

            $db->query('BEGIN TRANSACTION;');
            $stmtGroup = $db->prepare('INSERT INTO client_by_group (client_id,group_id) VALUES(:id,:gid);');
            if (!$stmtGroup) {
                throw new Exception('While preparing INSERT INTO statement: ' . $db->lastErrorMsg());
            }

            if (!$stmtGroup->bindValue(':id', intval($clientID), SQLITE3_INTEGER)) {
                throw new Exception('While binding id: ' . $db->lastErrorMsg());
            }

            if (!$stmtGroup->bindValue(':gid', intval($gid), SQLITE3_INTEGER)) {
                throw new Exception('While binding gid: ' . $db->lastErrorMsg());
            }

            if (!$stmtGroup->execute()) {
                throw new Exception('While executing INSERT INTO statement: ' . $db->lastErrorMsg());
            }
            $db->query('COMMIT;');
            $APIjson = array(
                "state" => "success",
                "client_ID" => $clientID,
                "group_ID" => $gid
            );
            header('Content-type: application/json');
            echo json_encode($APIjson);
		
		break;
		case "remove_client":
            $stmt = $db->prepare('DELETE FROM client_by_group WHERE client_id=:id');
            if (!$stmt) {
                throw new Exception('While preparing client_by_group statement: ' . $db->lastErrorMsg());
            }

            if (!$stmt->bindValue(':id', intval($_POST['id']), SQLITE3_INTEGER)) {
                throw new Exception('While binding id to client_by_group statement: ' . $db->lastErrorMsg());
            }

            if (!$stmt->execute()) {
                throw new Exception('While executing client_by_group statement: ' . $db->lastErrorMsg());
            }

            $stmt = $db->prepare('DELETE FROM client WHERE id=:id');
            if (!$stmt) {
                throw new Exception('While preparing client statement: ' . $db->lastErrorMsg());
            }

            if (!$stmt->bindValue(':id', intval($_POST['id']), SQLITE3_INTEGER)) {
                throw new Exception('While binding id to client statement: ' . $db->lastErrorMsg());
            }

            if (!$stmt->execute()) {
                throw new Exception('While executing client statement: ' . $db->lastErrorMsg());
            }

            $reload = true;
            $APIjson = array(
                "state" => "success"
            );
            header('Content-type: application/json');
            echo json_encode($APIjson);

		break;
        case "get_clients":
            $QUERYDB = getQueriesDBFilename();
            $FTLdb = SQLite3_connect($QUERYDB);
            $query = $db->query('SELECT * FROM client;');
            if (!$query) {
                throw new Exception('Error while querying gravity\'s client table: ' . $db->lastErrorMsg());
            }

            $data = array();
            while (($res = $query->fetchArray(SQLITE3_ASSOC)) !== false) {
                $group_query = $db->query('SELECT group_id FROM client_by_group WHERE client_id = ' . $res['id'] . ';');
                if (!$group_query) {
                    throw new Exception('Error while querying gravity\'s client_by_group table: ' . $db->lastErrorMsg());
                }

                $stmt = $FTLdb->prepare('SELECT name FROM network WHERE id = (SELECT network_id FROM network_addresses WHERE ip = :ip);');
                if (!$stmt) {
                    throw new Exception('Error while preparing network table statement: ' . $db->lastErrorMsg());
                }

                if (!$stmt->bindValue(':ip', $res['ip'], SQLITE3_TEXT)) {
                    throw new Exception('While binding to network table statement: ' . $db->lastErrorMsg());
                }

                $result = $stmt->execute();
                if (!$result) {
                    throw new Exception('While executing network table statement: ' . $db->lastErrorMsg());
                }

                // There will always be a result. Unknown host names are NULL
                $name_result = $result->fetchArray(SQLITE3_ASSOC);
                $res['name'] = $name_result['name'];

                $groups = array();
                while ($gres = $group_query->fetchArray(SQLITE3_ASSOC)) {
                    array_push($groups, $gres['group_id']);
                }
                $res['groups'] = $groups;
                array_push($data, $res);
            }

            header('Content-type: application/json');
            echo json_encode(array('data' => $data));
		break;
		
		default:
			header('HTTP/1.0 400 Bad Request');
    		echo '{"error" : "bad_request", "error_description" : "No process was provided with your request. Please check again."}';
    		exit;
	}
}else{
	header('HTTP/1.0 401 Unauthorized');
    echo '{"error" : "access_denied", "error_description" : "An invalid token was entered. Please check the token and try again."}';
    exit;
}
?>