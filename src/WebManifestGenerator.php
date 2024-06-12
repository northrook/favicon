<?php

declare( strict_types = 1 );

namespace Northrook\Favicon;

use JsonException;
use Northrook\Logger\Log;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

// todo: https://web.dev/articles/window-controls-overlay


/**
 * @template Title of string     // The shortest descriptor - `$short_name` alias
 * @template Name of ?string        // Meta Title equivalent
 * @template Description of ?string // The description of the application
 *
 */
final class WebManifestGenerator {


    public const Display_FullScreen = 'fullscreen';
    public const Display_Standalone = 'standalone';
    public const Display_MinimalUI  = 'minimal-ui';
    public const Display_OverlayUI  = 'window-controls-overlay';
    public const Display_Browser    = 'browser';

    /**
     * @var string<Title>
     */
    public string $short_name; // required
    /**
     * @var ?string<Name>
     */
    public ?string $name;
    /**
     * @var ?string<Description>
     */
    public ?string $description = null;

    public ?string $theme_color      = null;
    public ?string $background_color = null;

    // URL parameter ?source=%source% is appended to all URLs in the manifest
    public string $source = '?source=pwa';

    public string  $id        = '/';
    public ?string $start_url = null; // required
    public ?string $scope     = null;
    /**
     * @var string = [ 'fullscreen', 'standalone', 'minimal-ui', 'window-controls-overlay', 'browser' ][ $any ]
     */
    public string $display = 'standalone';

    private array $icons       = [];
    private array $screenshots = [];
    private array $shortcuts   = [];

    public function set(
        string $title,
        ?string $name = null,
        ?string $description = null,
    ) : WebManifestGenerator {
        $this->short_name  = $title;
        $this->name        = $name;
        $this->description = $description;

        return $this;
    }

    public function theme(
        string $color,
        ?string $backgroundColor = null,
    ) : WebManifestGenerator {
        $this->theme_color      = $color;
        $this->background_color = $backgroundColor;

        return $this;
    }

    public function application(
        string $id,
        ?string $startUrl = null,
        ?string $scope = null,
    ) : void {
        $this->id        = $id;
        $this->start_url = $startUrl;
        $this->scope     = $scope;
    }

    public function icons( string | array $icons ) : WebManifestGenerator {
        $this->icons = $this->decode( $icons );
        return $this;
    }

    public function shortcuts( string | array $shortcuts ) : WebManifestGenerator {
        $this->shortcuts = $this->decode( $shortcuts );
        return $this;
    }

    public function screenshots( string | array $screenshots ) : WebManifestGenerator {
        $this->screenshots = $this->decode( $screenshots );
        return $this;
    }

    public function toArray() : array {
        $manifest = [
            'short_name'       => $this->short_name ?? $this->name,
            'name'             => isset( $this->short_name ) ? $this->name : null,
            'description'      => $this->description,
            'icons'            => $this->icons,   // required
            'id'               => $this->source( $this->id ),
            'start_url'        => $this->source( $this->start_url ?? $this->id ),
            'scope'            => $this->scope ?? $this->id,
            'theme_color'      => $this->theme_color,
            'background_color' => $this->background_color,
            'display_override' => $this->display === 'window-controls-overlay' ? [ WebManifestGenerator::Display_OverlayUI, WebManifestGenerator::Display_MinimalUI ] : null,
            'display'          => $this->display === 'window-controls-overlay' ? WebManifestGenerator::Display_Standalone : $this->display,
            'screenshots'      => $this->screenshots,
            'shortcuts'        => $this->shortcuts,
        ];

        return array_filter( $manifest );
    }

    public function generate() : ?string {

        $manifest = $this->toArray();

        try {
            return json_encode( $manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        } catch ( JsonException $exception ) {
            Log::Error(
                'Manifest JSON encoding failed: {error}',
                [
                    'error'     => $exception->getMessage(),
                    'exception' => $exception,
                    'manifest'  => $this,
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