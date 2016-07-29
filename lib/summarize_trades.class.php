<?php

require_once( __DIR__ . '/strict_mode.funcs.php' );
require_once( __DIR__ . '/trades.class.php' );
require_once( __DIR__ . '/btcutil.class.php' );

class summarize_trades {
    private $ts_multiplier = 1000;  // use milliseconds
    
    private function summarize( $trades ) {
    }

    public function get_trade_summaries_minutes( $criteria ) {
        
        // align to start of minute
        $criteria['interval'] = 'minute';
        
        return $this->get_trade_summaries( $criteria );
    }

    public function get_trade_summaries_10_minutes( $criteria ) {
        
        // align to start of 10 minutes
        $criteria['interval'] = '10_minute';
        return $this->get_trade_summaries( $criteria );
    }
    
    public function get_trade_summaries_hours( $criteria ) {
        
        // align to start of hour
        $criteria['interval'] = 'hour';
        
        return $this->get_trade_summaries( $criteria );
    }

    public function get_trade_summaries_days( $criteria ) {
        
        // align to start of day
        $criteria['interval'] = 'day';
        
        return $this->get_trade_summaries( $criteria );
    }

    public function get_trade_summaries_weeks( $criteria ) {
        
        // align to start of first day of week.
        $criteria['interval'] = 'week';
        
        return $this->get_trade_summaries( $criteria );
    }

    public function get_trade_summaries_months( $criteria ) {
        
        // align to start of first day of month.
        $criteria['interval'] = 'month';
        
        return $this->get_trade_summaries( $criteria );
    }
    
    public function get_trade_summaries_years( $criteria ) {
        
        // align to start of first day of year.
        $criteria['interval'] = 'year';
        
        return $this->get_trade_summaries( $criteria );
    }
    
    
    /**
     * criteria keys:
     *  + market: eg 'dash_btc', or 'all'. required.
     *  + interval.  required. in seconds.
     *  + datetime_from: timestamp utc. required.
     *  + datetime_to: timestamp utc.  required.
     *  + direction: buy, sell
     *  + integeramounts: bool.  default = true.
     *  + fillgaps: bool.  default = false.
     *  + fields: array -- fields to return.
     *      available:  "period_start", "open", "close",
     *                  "high", "low", "avg", "volume"
     */
    private function get_trade_summaries( $criteria ) {
        extract( $criteria );
        $tradesobj = new trades();
        unset( $criteria['fields'] );
        $criteria['sort'] = 'asc';
        $criteria['integeramounts'] = true;
        $trades = $tradesobj->get_trades( $criteria );

        $intervals = [];
        foreach( $trades as $trade ) {
            $traded_at = $trade['tradeDate'] / 1000;
            $interval_start = $this->interval_start($traded_at, $interval)*$this->ts_multiplier;
            if( !isset($intervals[$interval_start]) ) {
                $intervals[$interval_start] = ['open' => 0,
                                               'close' => 0,
                                               'high' => 0,
                                               'low' => 0,
                                               'avg' => 0,
                                               'volume' => 0,
                                             ];
                $intervals_prices[$interval_start] = [];
            }
            $period =& $intervals[$interval_start];
            $price = $trade['tradePrice'];
            $intervals_prices[$interval_start][] = $price;
            
            if( $price ) {
                $period['period_start'] = $interval_start;
                $period['open'] = @$period['open'] ?: $price;
                $period['close'] = $price;
                $period['high'] = $price > $period['high'] ? $price : $period['high'];
                $period['low'] = $price > $period['low'] ? $price : $period['low'];
                $period['avg'] = array_sum($intervals_prices[$interval_start]) / count($intervals_prices[$interval_start]);
                $period['volume'] += $trade['tradeAmount'];
            }
        }
        
        if( !@$integeramounts ) {
            foreach( $intervals as &$period ) {
                $period['open'] = btcutil::int_to_money4( $period['open'] );
                $period['close'] = btcutil::int_to_money4( $period['close'] );
                $period['high'] = btcutil::int_to_money4( $period['high'] );
                $period['low'] = btcutil::int_to_money4( $period['low'] );
                $period['avg'] = btcutil::int_to_money4( $period['avg'] );
                $period['volume'] = btcutil::int_to_btc( $period['volume'] );
            }
        }
        
        // generate intervals in gaps.
        // note:  this is a slow operation.  best not to use this option if possible.
        if( @$fillgaps ) {
            $secs = $this->interval_secs( $interval );
            $next = $datetime_from;
            $prev_close = 1;
            while( $next < $datetime_to ) {
                $interval_start = $this->interval_start($next, $interval)*$this->ts_multiplier;
                $cur = @$intervals[$interval_start];
                if( !$cur ) {
                    $cur = ['period_start' => $interval_start,
                            'open' => $prev_close,
                            'close' => $prev_close,
                            'high' => $prev_close,
                            'low' => $prev_close,
                            'avg' => $prev_close,
                            'volume' => 0,
                          ];
                    $intervals[$interval_start] = $cur;
                }
                else {
                    $prev_close = $cur['close'];
                }
                $next += $secs;
            }
            ksort( $intervals );
        }

        // convert to user specified field order list, if present.
        if( @$fields ) {
            foreach( $intervals as $k => &$period ) {
                $p = [];
                foreach( $fields as $f ) {
                    $p[$f] = @$period[$f];
                }
                $period = $p;
            }
        }
        return array_values( $intervals );
    }
    
    private function interval_start( $ts, $interval ) {
        switch( $interval ) {
            case 'minute':
                return (int)($ts - ($ts % 60));
            case '10_minute':
                return (int)($ts - ($ts % 600));
            case 'hour':
                return (int)($ts - ($ts % 3600));
            case 'day':
                return strtotime( 'midnight today', $ts);
            case 'week':
                return strtotime( "midnight sunday last week", $ts );
            case 'month':
                return strtotime( "midnight first day of this month", $ts );
            case 'year':
                return strtotime( "midnight first day of january", $ts );
            default:
                throw new exception( "Unsupported interval" );
        }
    }

    private function interval_end( $ts, $interval ) {
        switch( $interval ) {
            case '10_minute':
                return ($this->interval_start($ts, $interval) + 600 -1);
            default:
                return (strtotime("+1 $interval", $ts) -1);
        }
    }
    
    private function interval_secs( $interval ) {
        $start = $this->interval_start( time(), $interval );
        return $this->interval_end( $start, $interval ) - $start;
    }
    
    
}
