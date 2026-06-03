<?php
define('STOPWORDS', ['a','al','ante','aquel','aquella','aquello','aqui','asi','aun','aunque','bajo','bien','cada','casi','cierto','como','con','conmigo','contra','cual','cuales','cualquier','cuan','cuando','cuanta','cuantas','cuanto','cuantos','de','del','demas','demasiado','dentro','desde','donde','dos','e','el','ella','ellas','ellos','en','entonces','entre','era','eran','eras','eres','es','esa','esas','ese','eso','esos','esta','estaba','estado','estamos','estan','estar','estas','este','esto','estos','estoy','estuvo','ex','excepto','fue','han','has','hasta','hay','hubo','junto','la','las','le','les','lo','los','mas','me','mi','mia','mias','mio','mios','mis','misma','mismas','mismo','mismos','muy','nada','nadie','ni','ningun','ninguna','ninguno','no','nos','nosotras','nosotros','nuestra','nuestras','nuestro','nuestros','o','os','otra','otras','otro','otros','para','pero','poca','pocas','poco','pocos','poder','podra','podran','podria','podrian','por','porque','primero','puede','pueden','puedo','que','quien','quienes','quiere','se','sea','sean','segun','ser','sera','seran','seria','serian','si','sido','siendo','sin','sino','sobre','solo','somos','son','soy','su','sus','tal','tambien','tampoco','tan','tanto','te','tener','tenga','tengo','tenia','tiene','tienen','tienes','toda','todas','todo','todos','tu','tus','tuvo','un','una','unas','uno','unos','usted','va','vamos','van','varias','varios','y','ya','yo']);

function limpiar_texto($texto) {
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = preg_replace('/[^\w\sáéíóúñü]/u', ' ', $texto);
    $tokens = preg_split('/\s+/', trim($texto));
    $tokens = array_filter($tokens, function($t) {
        return !in_array($t, STOPWORDS);
    });
    return implode(' ', $tokens);
}
