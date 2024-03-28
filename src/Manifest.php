<?php

declare(strict_types=1);

namespace Northrook\Favicon;

use Northrook\Favicon\Manifest\Display;
use Northrook\Types\Color\Hex;

// todo: https://web.dev/articles/window-controls-overlay

final class Manifest
{

    private array $manifest = [
        'name'             => null, // required
        'short_name'       => null,
        'description'      => null,
        'icons'            => [],   // required
        'id'               => null, // required
        'start_url'        => null,
        'scope'            => null,
        'theme_color'      => null,
        'background_color' => null,
        'display'          => Display::Standalone,
        'screenshots'      => [],
        'shortcuts'        => [],
    ];


    public function __construct(
        public string  $name,
        public ?string $shortName = null,
        public ?string $description = null,
        public ?Hex     $themeColor = null,
        public Display $display = Display::Standalone,
    ) {}


    public function application(
        string  $id,
        ?string $startUrl = null,
        ?string $scope = null,
    ) : void {
        $this->manifest[ 'id' ]        = $id;
        $this->manifest[ 'start_url' ] = $startUrl ?? $id;
        $this->manifest[ 'scope' ]     = $scope ?? $id;
    }

    public function colors(
        string  $themeColor,
        ?string $backgroundColor = null,
    ) : void {
        $this->manifest[ 'theme_color' ] = (string) ( new Hex( $themeColor ) );
        if ( $backgroundColor ) {
            $this->manifest[ 'background_color' ] = (string) ( new Hex( $backgroundColor ) );
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


    public function generate() : string {


        $display = ( Display::OverlayUI === $this->display ) ? [
            'display_override' => [ Display::OverlayUI, Display::MinimalUI ],
            'display'          => Display::Standalone,
        ] : [ 'display' => $this->display ];

        $this->manifest = array_merge(
            $this->manifest, $display, [
            'name'        => $this->name,
            'short_name'  => $this->shortName,
            'description' => $this->description,
        ],
        );

        $this->manifest = array_filter( $this->manifest );

        return json_encode( $this->manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES );
    }

    private function decode( null | array | string $value ) : ?array {
        return is_string( $value ) ? json_decode( $value, true ) : $value;
    }
}