<?php

require_once( __DIR__ . '/settings.class.php' );
require_once( __DIR__ . '/strict_mode.funcs.php' );

class currencies {

    /**
     * returns all currences, sorted by code, with type identifer.
     */
    public function get_all_currencies() {
        
        static $result = null;
        if( $result ) {
            return $result;
        }
        
        $fiat = $this->get_all_fiat();
        foreach( $fiat as &$f ) {
            $f['type'] = 'fiat';
        }
        
        $crypto = $this->get_all_crypto();
        foreach( $crypto as &$c ) {
            $c['type'] = 'fiat';
        }
        $all = array_merge( $fiat, $crypto );
        ksort( $all );
        $result = $all;
        
        return $result;
    }

    /**
     * returns all fiat currences, sorted by code
     */
    public function get_all_fiat() {
        $json_file = settings::get('data_dir') . '/fiat_currency_list.json';
        
        static $result = null;
        if( $result ) {
            return $result;
        }
        $buf = file_get_contents($json_file);
        $start = strpos( $buf, '[');
        $result = $this->add_keys( json_decode( substr($buf, $start), true ) );
        return $result;
    }
    
    /**
     * returns all crypto currences, sorted by code
     */
    public function get_all_crypto() {
        $json_file = settings::get('data_dir') . '/crypto_currency_list.json';
        
        static $result = null;
        if( $result ) {
            return $result;
        }
        $buf = file_get_contents($json_file);
        $start = strpos( $buf, '[');
        $result = $this->add_keys( json_decode( substr($buf, $start), true ) );
        return $result;
    }

    /**
     * turns currency list into assoc array for fast lookup by symbol.
     */
    private function add_keys( $list ) {
        $r = [];
        foreach( $list as $v ) {
            $k = $v['code'];
            $r[$k] = $v;
        }
        return $r;
    }
    
}
