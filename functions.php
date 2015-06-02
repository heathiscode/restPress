<?php
/* */
ini_set('display_errors',true);

require_once("vendor/phpQuery/phpQuery/phpQuery.php");
require_once("vendor/mustache.php/src/Mustache/Autoloader.php");
Mustache_Autoloader::register();

define('RESTPRESS_TEMPLATES',__DIR__.'/templates/');
define('RESTPRESS_COMPONENT','components');
define('RESTPRESS_LAYOUT','layouts');

// Register Custom Post Type
function initializeSettings() {

//This also helps us define a 'not' type for our ACF template drop down (which won't let us select 'all', so this is the same diff)

	$labels = array(
		'name'                => _x( 'Site Option Data', 'Post Type General Name', 'text_domain' ),
		'singular_name'       => _x( 'Site Option Data', 'Post Type Singular Name', 'text_domain' ),
		'menu_name'           => __( 'Post Type', 'text_domain' ),
		'name_admin_bar'      => __( 'Post Type', 'text_domain' ),
		'parent_item_colon'   => __( 'Parent Item:', 'text_domain' ),
		'all_items'           => __( 'All Items', 'text_domain' ),
		'add_new_item'        => __( 'Add New Item', 'text_domain' ),
		'add_new'             => __( 'Add New', 'text_domain' ),
		'new_item'            => __( 'New Item', 'text_domain' ),
		'edit_item'           => __( 'Edit Item', 'text_domain' ),
		'update_item'         => __( 'Update Item', 'text_domain' ),
		'view_item'           => __( 'View Item', 'text_domain' ),
		'search_items'        => __( 'Search Item', 'text_domain' ),
		'not_found'           => __( 'Not found', 'text_domain' ),
		'not_found_in_trash'  => __( 'Not found in Trash', 'text_domain' ),
	);
	$args = array(
		'label'               => __( 'option_data', 'text_domain' ),
		'labels'              => $labels,
		'supports'            => array( ),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => false,
		'show_in_menu'        => false,
		'menu_position'       => 5,
		'show_in_admin_bar'   => false,
		'show_in_nav_menus'   => false,
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => true,
		'capability_type'     => 'page',
	);
	register_post_type( 'option_data', $args );
	
	register_nav_menus();

}

function niceName($str) {
    return ucwords(substr($str,0,strrpos($str,'.')));
}

function acf_load_template_field_choices( $field ) {
    // reset choices
    $field['choices'] = array(''=>'Default');
    $d = scandir(RESTPRESS_TEMPLATES.RESTPRESS_LAYOUT);
    foreach ($d as $layout) {
        if ($layout[0]=='.' || $layout=='main.html') continue;
        $field['choices'][$layout] = niceName($layout);
    }
    return $field;
}

function acf_load_component_field_choices( $field ) {
    // reset choices
    $field['choices'] = array('*'=>'All');
    $d = scandir(RESTPRESS_TEMPLATES.RESTPRESS_COMPONENT);
    foreach ($d as $layout) {
        if ($layout[0]=='.' || $layout=='main.html') continue;
        $field['choices'][$layout] = niceName($layout);
    }
    return $field;
}


/* SERVICE / TEMPLATE END */

function parseUrlQuery() {
    $url = $_SERVER['REQUEST_URI'];
    $parts = explode('/',$url);
    for ($i=1; $i<count($parts); $i+=2){
        $query[$parts[$i]]=$parts[$i+1];
    }
    return $query;
}

function _todash($str) { return str_replace('_','-',$str); }
function _tolowdash($str) { return strtolower(str_replace('_','-',$str)); }

function populateBlock($el,$data) {
    $html = $el->html();
    $html = str_replace('%7B%7B','{{',$html);
    $html = str_replace('%7D%7D','}}',$html);
    $count = 0;
    $m = new Mustache_Engine;
    $el->replaceWith($m->render($html, $data));
}

function fetchData($path,$query) {
    $url = "http://localhost:8080/wp-json/{$path}?".http_build_query(array('filter'=>$query,'page'=>$query['paged']));
    $username = "admin"; 
    $password = "password";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    preg_match_all("/(.+): (.+)\r\n/",$header,$out);
    $head = [];
    foreach ($out[1] as $k=>$v) {
        $head[_tolowdash($v)]=$out[2][$k];
    }
    $bodyData = json_decode($body);
    $data['response']=$bodyData;
    $data['header']=$head;
    return $data;
}

function buildPaging($totalPages) {
    $self = $_SERVER['REQUEST_URI'];
    $_self = substr($self,0,strpos($self,'/page'));
    if ($_self) $self=$_self;
    $pages = array();
    $first = true;
    
    for ($p=1; $p<=$totalPages; $p++) {
        $active = $currentPage == $p?true:false;
        array_push($pages,array('page'=>$p,'link'=>$self.'/page/'.$p,'first'=>$first,'last'=>$last,'active'=>$active,'helper-class'=> ($first?'paginateFirst ':'').($active?'paginateCurrent':'').($last?'paginateLast':'') ));
        $first = false;
    }
    
    $pages[count($pages)-1]['last']=true;
    return $pages;
}

function loadComponents($pq,$data) {
    static $recurse=0;
    $components = $data['components'];
    if (!is_array($components)) $components=[];
    
    foreach ($pq['component'] as $comp) {
        $attrs=$comp->attributes;
        $comp = pq($comp);
        $name = $comp->attr('name');
        $str = "";
        if ( $components[0]=='*' || in_array($name.'.html',$components)) {
            $getFile = RESTPRESS_TEMPLATES.RESTPRESS_COMPONENT.'/'.$comp->attr('name').'.html';
            $str = file_get_contents($getFile);
            foreach ($attrs as $attr) {
                $str = str_replace('{{'.$attr->nodeName.'}}',$attr->nodeValue,$str);
            }
        }
        $comp->replaceWith($str);
    }
}

function loadMenus($pq,&$data) {
    foreach ($pq['menu'] as $menu) {
        $name = pq($menu)->attr('name');
        $menu = wp_get_nav_menu_items($name);
        $data['menu'][$name] = $menu;
    }
    return null;
}

function compileDirectives($pq) {
    $queries = array(
        'fetch'=>array(
            'm','p','posts','w','cat','withcomments','withoutcomments','s','search','exact','sentence','calendar','page','paged','more','tb','pb','author','order','orderby','year','monthnum','day','hour','minute','second','name','category_name','tag','feed','author_name','static','pagename','page_id','error','comments_popup','attachment','attachment_id','subpost','subpost_id','preview','robots','taxonomy','term','cpage','post_type','posts_per_page'
        )
    );
    
    $directives = array("fetch"/*,... other things */);
    
    $currentPage = ($currentPage = get_query_var('paged'))?$currentPage:1;
    $count = 0;
    foreach ($directives as $directive) {
        foreach ($pq[$directive] as $_el) {
            $attrs = $el->attributes;
            $el = pq($_el);
            
            foreach ($queries[$directive] as $attr) {
                $a = $el->attr($attr);
                if (!empty($a)) $query[$attr] = $a;
            }
            
            if (count($query)) {
                $query['paged'] = get_query_var('paged');
                $data = fetchData('posts',$query);
            } else {
                $data = [];
            }
   
            $totalPages=$data['header']['x-wp-totalpages'];
            $data['query']=$query;
            $data['totalPages']=$totalPages;
            $data['pages']=buildPaging($totalPages);
            
            populateBlock($el,$data);
        }
    }
    
    foreach ($pq['menu'] as $menu) {
        $menu = pq($menu);
        $menu->replaceWith($menu->html());
    }
      
}

function fetchCurrentPage() {
    global $wp_query;
    
    if ( !empty($wp_query->query_vars['page_id']) || !empty($wp_query->query_vars['p']) )  {
        $data = fetchData("posts/".$post->ID,array());
    } else {
        $data = fetchData("posts/",$wp_query->query_vars);
    }
    
    $fields = getCustomTemplateFields();
    $data['components']=$fields['components'];
    return $data;
}

function getCustomTemplateFields() {
    global $wp_query;
    $template = get_field('template', $wp_query->queried_object);
    $components = get_field('components', $wp_query->queried_object);
    return array('template'=>$template,'components'=>$components);
}

function getCurrentTemplate() {
    $fields = getCustomTemplateFields();
    if (!empty($fields['template'])) {
        $pq =phpQuery::newDocumentFile(RESTPRESS_TEMPLATES.RESTPRESS_LAYOUT."/{$fields['template']}");
    } else {
        $pq =phpQuery::newDocumentFile(RESTPRESS_TEMPLATES.RESTPRESS_LAYOUT.'/main.html');
    }
    
    return $pq;
}

/* Top level processing for pages,posts,category/tags/terms */
function processPage($pq, $data) {
    global $post, $wp_query;    
    $fields = getCustomTemplateFields();
    /* some evil trickery to get 'related' content */
    if (is_array($data['response'])) foreach ($data['response'] as $set) {
        $terms = $set->terms;
        $category = $terms->category;
        $post_tag = $terms->post_tag;
        if (is_array($category)) {
            foreach ($terms->category as $cat) {
                if ($cat->parent) {
                    $category_slug = $cat->slug;
                    $category_name = $cat->name;
                }
            }
            if (!$category_slug) {
                $category_slug = $terms->category[0]->slug;
                $category_name = $terms->category[0]->name;
            }
        }
        if (is_array($post_tag)) {
            foreach ($terms->post_tag as $tag) {
                $tag_slug = $tag->slug;
                $tag_name = $tag->name;
            }
        }
    }
    
    /* Additional helper data to associate with Item */
    $data['current_template']=$fields['template'];
    $data['template_url']=get_template_directory_uri();
    $data['pages']=buildPaging($data['totalPages']);
    
    if ($category_slug) {
        $d = fetchData("posts/",array('post__not_in'=>array($post->ID),'category_name'=>$category_slug));
        $data['related']['category']['response']=$d['response'];
        $data['related']['category']['slug']=$category_slug;
        $data['related']['category']['name']=$category_name;
    }
    if ($tag_slug) {
        $d = fetchData("posts/",array('post__not_in'=>$post->ID,'tag'=>$tag_slug));
        $data['related']['tag']=$d['response'];
    }
    if (is_array($data['response'][0])) foreach ($data['response'][0] as $k=>$v) $data[$k]=$v;
    
    //markup the head and body
    populateBlock($pq['head'],$data);
    populateBlock($pq['body'],$data);
    
    return $pq;
}
 

add_filter('acf/load_field/name=template', 'acf_load_template_field_choices');
add_filter('acf/load_field/name=components', 'acf_load_component_field_choices');
add_filter( 'query_vars', function( $vars ){
    $vars[] = 'post__in';
    $vars[] = 'post__not_in';
    return $vars;
});

add_action( 'init', 'initializeSettings', 0 );

