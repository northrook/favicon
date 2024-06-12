<?php

declare( strict_types = 1 );

namespace Northrook\Favicon;

use DOMDocument;
use DOMElement;
use DOMException;
use Northrook\Logger\Log;
use Stringable;

final readonly class BrowserConfigGenerator implements Stringable {

    private DOMDocument $browserConfig;

    /**
     * @param null|string $tileColor Hex
     * @param array       $icons     [ 'localName' => 'iconPath.png')
     * @param string      $dir       Relative to public root
     */
    public function __construct(
        ?string $tileColor,
        array $icons = [],
        string $dir = '/',
    ) {
        $this->browserConfig = new DOMDocument( '1.0', 'UTF-8' );

        $browserconfig = $this->element( 'browserconfig' );
        $application   = $this->element( 'msapplication' );
        $tile          = $this->element( 'tile' );

        if ( $tileColor ) {
            $tile->appendChild( $this->element( 'TileColor', $tileColor ) );
        }

        foreach ( $icons as $localName => $iconPath ) {
            $icon = $this->element( $localName );
            $path = $dir . ltrim( $iconPath, '/' );
            $icon->setAttribute( 'src', $path );
            $tile->appendChild( $icon );
        }

        $application->appendChild( $tile );
        $browserconfig->appendChild( $application );
        $this->browserConfig->appendChild( $browserconfig );
    }


    /**
     * Exception wrapper for {@see DOMDocument::createElement}.
     *
     * @param string $localName
     * @param string $value
     *
     * @return false|DOMElement
     */
    private function element( string $localName, string $value = '' ) : ?DOMElement {
        try {
            return $this->browserConfig->createElement( $localName, $value );
        } catch ( DOMException $exception ) {
            Log::Error(
                message : 'Unable to create {name} element.',
                context : [ 'name' => $localName, 'exception' => $exception, ],
            );
            return false;
        }
    }

    public function xml( bool $pretty = true, bool $trim = true ) : string {

        $this->browserConfig->formatOutput = $pretty;

        return $trim ? trim( $this->browserConfig->saveXML() ) : $this->browserConfig->saveXML();
    }

    public function __toString() : string {
        return $this->xml();
    }
}