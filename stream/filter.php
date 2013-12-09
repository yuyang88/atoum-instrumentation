<?php

namespace atoum\instrumentation\stream;

use atoum\instrumentation\sequence\matching;

class filter extends \php_user_filter {

    protected $_buffer = null;

    public function filter ( $in, $out, &$consumed, $closing ) {

        $return = PSFS_FEED_ME;

        while($iBucket = stream_bucket_make_writeable($in)) {

            $this->_buffer .= $iBucket->data;
            $consumed      += $iBucket->datalen;
        }

        if(null !== $consumed)
            $return = PSFS_PASS_ON;

        if(true === $closing) {

            $this->compute();
            $bucket = stream_bucket_new(
                $this->getStream(),
                $this->_buffer
            );
            $oBucket = stream_bucket_make_writeable($out);
            stream_bucket_append($out, $bucket);

            $return        = PSFS_PASS_ON;
            $this->_buffer = null;
        }

        return $return;
    }

    public function onCreate ( ) {

        return true;
    }

    public function onClose ( ) {

        return;
    }

    public function compute ( ) {

        $matching = new matching(token_get_all($this->_buffer));

        $rules      = array();
        $parameters = $this->getParameters();
        $enabled    = function ( $parameter ) use ( &$parameters ) {

            return    isset($parameters[$parameter])
                   && true === $parameters[$parameter];
        };
        $…          = matching::getFillSymbol();

        if(true === $enabled('nodes'))
            $rules['method::body'][] = array(
                array('if', '(', $…, ')'),
                array('if', '(', 'mark_cond(', '\3', ')', ')'),
                $matching::SHIFT_REPLACEMENT_END
            );

        if(true === $enabled('edges')) {

            $rules['method::body'][] = array(
                array('return', $…, ';'),
                array('mark_line(__LINE__)', ';', 'return ', '\2', ';'),
                $matching::SHIFT_REPLACEMENT_END
            );
            $rules['method::body'][] = array(
                array(';'),
                array(';', 'mark_line(__LINE__)', ';'),
                $matching::SHIFT_REPLACEMENT_END
            );
        }

        if(true === $enabled('moles'))
            $rules['method::start'][] = array(
                array('{'),
                array('{', ' if(\atoum\instrumentation\mole::exists(\'\class.name::\method.name\')) return \atoum\instrumentation\mole::call(\'\class.name::\method.name\', func_get_args());'),
                $matching::SHIFT_REPLACEMENT_END
            );

        $matching->skip(array(T_WHITESPACE));
        $matching->match($rules);

        $buffer = null;

        foreach($matching->getSequence() as $token)
            if(is_array($token))
                $buffer .= $token[$matching::TOKEN_VALUE];
            else
                $buffer .= $token;

        $this->_buffer = $buffer;

        return;
    }

    public function setName ( $name ) {

        $old              = $this->filtername;
        $this->filtername = $name;

        return $old;
    }

    public function setParameters ( $parameters ) {

        $old          = $this->params;
        $this->params = $parameters;

        return $old;
    }

    public function getName ( ) {

        return $this->filtername;
    }

    public function getParameters ( ) {

        return $this->params;
    }

    public function getStream ( ) {

        return $this->stream;
    }
}