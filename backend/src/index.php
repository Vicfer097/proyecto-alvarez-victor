<?php
declare(strict_types=1);
require __DIR__.'/functions.php';
date_default_timezone_set('America/Mexico_City');
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
$method = $_SERVER['REQUEST_METHOD'];
try {
    if ($path === '/health') {
        if ($method !== 'GET') { header('Allow: GET'); answer(['error'=>'Método no permitido.'],405); }
        $checks = ['db'=>false, 'redis'=>false];
        try { database()->query('SELECT 1'); $checks['db'] = true; } catch (Throwable $e) {}
        try { $checks['redis'] = (bool)cache()->ping(); } catch (Throwable $e) {}
        $ok = !in_array(false, $checks, true);
        answer(['status'=>$ok?'ok':'error', 'services'=>$checks], $ok?200:503);
    }
    $export = $path === '/actividades/exportar';
    if (!$export && !preg_match('#^/actividades(?:/([1-9][0-9]*))?$#', $path, $match)) answer(['error'=>'Ruta no encontrada.'],404);
    $id = isset($match[1]) ? filter_var($match[1], FILTER_VALIDATE_INT) : null;
    if ($id === false) answer(['error'=>'Identificador inválido.'],400);
    $allowed = $export ? ['GET'] : ($id ? ['GET','PUT','DELETE'] : ['GET','POST']);
    if (!in_array($method,$allowed,true)) { header('Allow: '.implode(', ',$allowed)); answer(['error'=>'Método no permitido.'],405); }
    $db = database();
    if ($export) {
        header('Content-Disposition: attachment; filename="actividades.json"');
        answer(['actividades'=>$db->query('SELECT * FROM actividades ORDER BY fecha_creacion DESC, id DESC')->fetchAll()]);
    }
    $redis = cache();
    if ($method === 'GET' && !$id) {
        $search = $_GET['buscar'] ?? '';
        $page = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>1000000]]);
        $limit = filter_var($_GET['limite'] ?? 5, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>100]]);
        if (!is_string($search) || mb_strlen($search)>150 || !$page || !$limit) answer(['error'=>'Búsqueda, página o límite inválidos.'],400);
        $search = trim($search);
        $counter = $redis->incr('contador_consultas');
        $generation = $redis->get('actividades:version') ?: '0';
        $key = 'actividades:listado:'.$generation.':'.hash('sha256',json_encode([$search,$page,$limit]));
        $stored = $redis->get($key);
        if ($stored !== false) {
            $result = json_decode($stored,true,512,JSON_THROW_ON_ERROR); $source = 'redis';
        } else {
            $where = $search === '' ? '' : ' WHERE titulo LIKE :titulo OR descripcion LIKE :descripcion';
            $params = $search === '' ? [] : ['titulo'=>'%'.$search.'%', 'descripcion'=>'%'.$search.'%'];
            $count = $db->prepare('SELECT COUNT(*) FROM actividades'.$where); $count->execute($params);
            $query = $db->prepare('SELECT * FROM actividades'.$where.' ORDER BY fecha_creacion DESC, id DESC LIMIT :limite OFFSET :offset');
            foreach ($params as $k=>$v) $query->bindValue(':'.$k,$v,PDO::PARAM_STR);
            $query->bindValue(':limite',$limit,PDO::PARAM_INT); $query->bindValue(':offset',($page-1)*$limit,PDO::PARAM_INT);
            $query->execute();
            $result = ['actividades'=>$query->fetchAll(),'total'=>(int)$count->fetchColumn(),'pagina'=>$page,'limite'=>$limit];
            $redis->setex($key,max(30,(int)getenv('CACHE_TTL')),json_encode($result,JSON_THROW_ON_ERROR)); $source = 'mariadb';
        }
        error_log('Listado atendido desde '.$source);
        answer($result+['origen'=>$source,'contador_consultas'=>$counter]);
    }
    $existing = null;
    if ($id) {
        $q = $db->prepare('SELECT * FROM actividades WHERE id = ?'); $q->execute([$id]); $existing=$q->fetch();
        if (!$existing) answer(['error'=>'Actividad no encontrada.'],404);
        if ($method === 'GET') answer(['actividad'=>$existing]);
    }
    if ($method === 'DELETE') {
        $q=$db->prepare('DELETE FROM actividades WHERE id = ?'); $q->execute([$id]);
        $redis->incr('actividades:version');
        answer(['mensaje'=>'Actividad eliminada.']);
    }
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''),'application/json')) answer(['error'=>'Se requiere application/json.'],415);
    $raw=file_get_contents('php://input');
    if (strlen($raw)>16000) answer(['error'=>'Solicitud demasiado grande.'],413);
    try { $input=json_decode($raw,true,512,JSON_THROW_ON_ERROR); } catch (JsonException $e) { answer(['error'=>'JSON inválido.'],400); }
    if (!is_array($input) || array_is_list($input)) answer(['error'=>'Se requiere un objeto JSON.'],400);
    [$data,$errors]=validateActivity($input);
    if ($errors) answer(['error'=>'Revisa los campos.','campos'=>$errors],422);
    if ($method==='POST') {
        $q=$db->prepare('INSERT INTO actividades (titulo,descripcion,estado,prioridad,fecha_limite) VALUES (:titulo,:descripcion,:estado,:prioridad,:fecha_limite)');
        $q->execute($data); $id=(int)$db->lastInsertId();
    } else {
        $q=$db->prepare('UPDATE actividades SET titulo=:titulo,descripcion=:descripcion,estado=:estado,prioridad=:prioridad,fecha_limite=:fecha_limite WHERE id=:id');
        $q->execute($data+['id'=>$id]);
    }
    $redis->incr('actividades:version');
    $activity=$data+['id'=>$id]; $sent=null;
    if ($method==='POST') $sent=notification($activity,'creada');
    elseif ($existing['estado']!=='completada' && $data['estado']==='completada') $sent=notification($activity,'completada');
    answer(['mensaje'=>$method==='POST'?'Actividad creada.':'Actividad actualizada.','actividad'=>$activity,'correo_enviado'=>$sent],$method==='POST'?201:200);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? null)===1062) answer(['error'=>'Ya existe una actividad con ese título.','campos'=>['titulo'=>'El título debe ser único.']],409);
    error_log('Error de base de datos: '.$e->getCode()); answer(['error'=>'No fue posible acceder a la base de datos.'],503);
} catch (Throwable $e) {
    error_log('Error del servicio: '.get_class($e)); answer(['error'=>'Servicio temporalmente no disponible.'],503);
}
