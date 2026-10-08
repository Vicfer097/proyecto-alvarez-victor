<?php
require __DIR__.'/../src/functions.php';
$valid=['titulo'=>'Actividad válida', 'descripcion'=>'Descripción', 'estado'=>'pendiente', 'prioridad'=>'alta', 'fecha_limite'=>'2026-10-08'];
$cases=[
    [$valid, []],
    [array_replace($valid,['titulo'=>' ab ']), ['titulo']],
    [array_replace($valid,['titulo'=>str_repeat('á',151)]), ['titulo']],
    [array_replace($valid,['descripcion'=>str_repeat('x',2001)]), ['descripcion']],
    [array_replace($valid,['estado'=>'otro','prioridad'=>'urgente']), ['estado','prioridad']],
    [array_replace($valid,['fecha_limite'=>'2026-02-30']), ['fecha_limite']],
    [array_replace($valid,['fecha_limite'=>null]), []],
    [array_replace($valid,['titulo'=>['invalido']]), ['titulo']],
    [array_replace($valid,['fecha_limite'=>'2026-2-3']), ['fecha_limite']],
];
foreach($cases as $i=>[$input,$expected]) {
    [$data,$errors]=validateActivity($input);
    if(array_keys($errors)!==$expected) { fwrite(STDERR,'Caso fallido: '.($i+1).PHP_EOL); exit(1); }
}
[$data,$errors]=validateActivity(array_replace($valid,['titulo'=>'  Revisión de actividades  ']));
if($data['titulo']!=='Revisión de actividades')exit(1);
echo '10 comprobaciones de validación correctas.'.PHP_EOL;
