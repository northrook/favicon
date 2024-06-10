<?php

declare( strict_types = 1 );

namespace Northrook\Favicon;

use JsonException;
use Northrook\Favicon\Manifest\Display;
use Northrook\Logger\Log;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

// todo: https://web.dev/articles/window-controls-overlay


/**
 *
 * @property  string  $title       // The shortest descriptor - `$short_name` alias
 * @property  string  $name        // Meta Title equivalent
 * @property  string  $short_name  // The name of the application
 * @property  string  $description // The description of the application
 * @property  string  $id
 * @property  Display $display
 * @property  string  $color
 * @property  string  $background
 */
final class Manifest {

    public string $source = '?source=pwa';

    public function __get( string $name ) : mixed {
        return $this->manifest[ $name ] ?? null;
    }

    public function __set( string $name, $value ) : void {
        $name = match ( $name ) {
            'title'      => 'short_name',
            'color'      => 'theme_color',
            'background' => 'background_color',
            default      => $name,
        };
        if ( array_key_exists( $name, $this->manifest ) ) {
            $this->manifest[ $name ] = $value;
        }
    }

    public function __construct(
        private array $manifest = [
            'name'             => null, // required
            'short_name'       => null,
            'description'      => null,
            'icons'            => [],   // required
            'id'               => '/', // required
            'start_url'        => null,
            'scope'            => null,
            'theme_color'      => null,
            'background_color' => null,
            'display'          => Display::Standalone,
            'screenshots'      => [],
            'shortcuts'        => [],
        ]
    ) {}

    public function __isset( string $name ) : bool {
        return isset( $this->manifest[ $name ] );
    }

    public function set(
        array $manifest
    ) : void {
        $this->manifest = array_merge( $this->manifest, $manifest );
    }

    public function application(
        string $id,
        ?string $startUrl = null,
        ?string $scope = null,
    ) : void {
        $this->manifest[ 'id' ]        = $id;
        $this->manifest[ 'start_url' ] = $startUrl ?? $id;
        $this->manifest[ 'scope' ]     = $scope ?? $id;
    }

    // TODO : Use a Color\Hex type when available
    public function colors(
        string $themeColor,
        ?string $backgroundColor = null,
    ) : void {
        $this->manifest[ 'theme_color' ] = $themeColor;
        if ( $backgroundColor ) {
            $this->manifest[ 'background_color' ] = $backgroundColor;
        }
    }

    public function icons( string | array $icons ) : void {
        $this->manifest[ 'icons' ] = $this->decode( $icons );
    }

    public function shortcuts( string | array $shortcuts ) : void {
        $this->manifest[ 'shortcuts' ] = $this->decode( $shortcuts );
    }

    public function screenshots( string | array $screenshots ) : void {
        $this->manifest[ 'screenshots' ] = $this->decode( $screenshots );
    }


    public function generate() : ?string {

        if ( $this->manifest[ 'display' ] === Display::OverlayUI ) {
            $index          = array_search( 'display', array_keys( $this->manifest ), true );
            $this->manifest = array_merge(
                array_slice( $this->manifest, 0, $index ),
                [
                    'display_override' => [ Display::OverlayUI, Display::MinimalUI ],
                    'display'          => Display::Standalone,
                ],
                array_slice( $this->manifest, $index ),
            );
        }

        if ( $this->manifest[ 'id' ] && ! $this->manifest[ 'start_url' ] ) {
            $this->manifest[ 'start_url' ] = $this->manifest[ 'id' ];
        }

        if ( $this->manifest[ 'id' ] && ! $this->manifest[ 'scope' ] ) {
            $this->manifest[ 'scope' ] = $this->manifest[ 'id' ];
        }


        $this->manifest[ 'id' ]        = $this->source( $this->manifest[ 'id' ] );
        $this->manifest[ 'start_url' ] = $this->source( $this->manifest[ 'start_url' ] );


        $manifest = array_filter( $this->manifest );

        try {
            return json_encode( $manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        } catch ( JsonException $exception ) {
            Log::Error(
                'Manifest JSON encoding failed: {error}',
                [
                    'error'     => $exception->getMessage(),
                    'exception' => $exception,
                    'manifest'  => $this->manifest,
                    'generated' => $manifest,
                ],
            );
            return null;
        }
    }


    private function source( string $value ) : string {
        return str_ends_with( $value, $this->source ) ? $value : $value . $this->source;
    }

    private function decode( null | array | string $value ) : ?array {
        try {
            return is_string( $value ) ? json_decode( $value, true, 512, JSON_THROW_ON_ERROR ) : $value;
        } catch ( JsonException $e ) {
            Log::Error( 'Manifest JSON decoding failed: {error}', [ 'error' => $e->getMessage(), 'exception' => $e, 'value' => $value ] );
            return null;
        }
    }
}