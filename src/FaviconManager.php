<?php

declare( strict_types = 1 );

namespace Northrook\Favicon;

use GdImage;
use Intervention\Image\Decoders\NativeObjectDecoder;
use Intervention\Image\Drivers\Gd\Decoders\FilePathImageDecoder;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use Northrook\Core\Cache\ManifestCache;
use Northrook\Core\Process\Status;
use Northrook\Support\File;
use Northrook\Type\Path;
use SVG\SVG;
use Symfony\Component\Stopwatch\Stopwatch;

final class FaviconManager {

    // TODO : Safari Pinned Tab Support
    public const SIZES = [
        'favicon-16x16.png'            => 16,
        'favicon-32x32.png'            => 32,
        'android-chrome-36x36.png'     => 36,
        'android-chrome-48x48.png'     => 48,
        'android-chrome-72x72.png'     => 72,
        'android-chrome-96x96.png'     => 96,
        'android-chrome-144x144.png'   => 144,
        'android-chrome-192x192.png'   => 192,
        'android-chrome-256x256.png'   => 256,
        'android-chrome-384x384.png'   => 384,
        'android-chrome-512x512.png'   => 512,
        'mstile-70x70.png'             => 70,
        'mstile-144x144.png'           => 144,
        'mstile-150x150.png'           => 150,
        'mstile-310x310.png'           => 310,
        'mstile-310x150.png'           => [ 310, 150 ],
        'apple-touch-icon.png'         => 57,
        'apple-touch-icon-57x57.png'   => 57,
        'apple-touch-icon-60x60.png'   => 60,
        'apple-touch-icon-72x72.png'   => 72,
        'apple-touch-icon-76x76.png'   => 76,
        'apple-touch-icon-114x114.png' => 114,
        'apple-touch-icon-120x120.png' => 120,
        'apple-touch-icon-144x144.png' => 144,
        'apple-touch-icon-152x152.png' => 152,
        'apple-touch-icon-180x180.png' => 180,
    ];

    public readonly Status               $status;
    public readonly ?SVG                 $svg;
    public readonly WebManifestGenerator $manifest;
    public readonly ManifestCache        $faviconAssetsCache;

    private readonly Path  $publicRoot;
    private readonly Image $image;

    public function __construct(
        string $publicRoot,
        ?ManifestCache $cache = null,
        ?Stopwatch $stopwatch = null,
        ?WebManifestGenerator $webManifest = null,
    ) {

        $this->publicRoot = new Path( $publicRoot );

        $this->faviconAssetsCache = $cache ?? new ManifestCache( 'faviconAssets' );

        // Assign Status Monitor
        $this->status = new Status( 'FaviconManager', $stopwatch );

        // Assign Manifest
        $this->manifest = $webManifest ?? new WebManifestGenerator();
    }

    public function generate( array $sizes = [ 16, 24, 32 ] ) : Status {

        if ( ! $this->publicRoot->isDir ) {
            throw new \LogicException( 'No' );
        }

        $this->purgeExisting();

        $document = [
            'meta' => [],
            'link' => [],
        ];

        $ico = new IcoFileGenerator(
            imagecreatefromstring( $this->image->toPng()->toString() ),
            $sizes
        );

        $ico->save( $this->publicRoot->toString( 'favicon.ico' ) );

        $document[ 'link' ][ 'favicon.ico' ] = [
            'rel'  => 'shortcut icon',
            'type' => 'image/x-icon',
            'href' => '/favicon.ico',
        ];

        if ( $this->svg ) {
            File::save(
                $this->publicRoot->toString( 'favicon.svg' ),
                $this->svg->toXMLString( false ),
            );
            $document[ 'link' ][ 'favicon.svg' ] = [
                'rel'  => 'icon',
                'type' => 'image/svg+xml',
                'href' => '/favicon.svg',
            ];
        }

        if ( $this->manifest->theme_color ) {
            $document[ 'meta' ][ 'theme-color' ] = [
                'name'    => 'theme-color',
                'content' => $this->manifest->theme_color,
            ];
        }


        $icons = [];
        $tiles = [];

        foreach ( FaviconManager::SIZES as $name => $size ) {

            $image  = clone $this->image;
            $width  = is_array( $size ) ? $size[ 0 ] : $size;
            $height = is_array( $size ) ? $size[ 1 ] : $size;
            $image->scaleDown( $width, $height );
            $image->save( $this->publicRoot->toString( $name ) );

            if ( str_starts_with( $name, 'favicon' ) ) {
                $document[ 'link' ][ strstr( $name, '.', true ) ] = [
                    'rel'  => 'icon',
                    'type' => 'image/png',
                    'href' => '/' . $name,
                ];
            }

            if ( $name === 'apple-touch-icon-180x180.png' ) {
                $image->save( $this->publicRoot->toString( 'apple-touch-icon.png' ) );
                $document[ 'link' ][ 'apple-touch-icon' ] = [
                    'rel'   => 'apple-touch-icon',
                    'sizes' => '180x180',
                    'href'  => '/apple-touch-icon.png',
                ];
            }

            if ( str_starts_with( $name, 'android-chrome' ) ) {
                $icons[] = [
                    'src'   => "/$name",
                    'sizes' => "{$size}x$size",
                    'type'  => 'image/png',
                ];
            }

            if ( is_int( $size ) && str_starts_with( $name, 'mstile' ) ) {
                $tiles[ "square{$size}x{$size}logo" ] = $name;
            }

            if ( $name === 'mstile-144x144.png' ) {
                $document[ 'meta' ][ 'msapplication-TileImage' ] = [
                    'name'    => 'msapplication-TileImage',
                    'content' => '/mstile-144x144.png',
                ];
            }
        }

        if ( $this->manifest->background_color ) {
            $document[ 'meta' ][ 'msapplication-TileColor' ] = [
                'name'    => 'msapplication-TileColor',
                'content' => $this->manifest->background_color,
            ];
        }

        $this->manifest->icons( $icons );

        $manifest      = $this->manifest->generate();
        $browserconfig = new BrowserConfigGenerator( $this->manifest->theme_color, $tiles );

        File::save( $this->publicRoot->toString( 'manifest.json' ), $manifest );
        File::save( $this->publicRoot->toString( 'browserconfig.xml' ), $browserconfig );

        $document[ 'link' ][ 'manifest' ] = [
            'rel'  => 'manifest',
            'type' => 'application/manifest+json',
            'href' => '/manifest.json',
        ];


        $this->faviconAssetsCache->set( $document );

        return $this->status;
    }

    public function setSource( SVG | Path | string $source ) : FaviconManager {

        $this->status->step( 'Set Source' );

        if ( $source instanceof SVG ) {
            $this->svg = $source;
            $this->status->step( 'Set Source' )->end(
                'success',
                'Assigned provided SVG.'
            );
            return $this;
        }

        $source = is_string( $source ) ? new Path( $source ) : $source;

        if ( ! $source->exists ) {
            $this->status->step( 'Set Source' )->end(
                'error',
                'Source file does not exist.'
            );
            return $this;
        }

        if ( ! str_contains( $source->mimeType, 'image/' ) ) {
            $this->status->step( 'Set Source' )->end(
                'error',
                'Source file is not an image.'
            );
            return $this;
        }

        if ( $source->mimeType === 'image/svg+xml' ) {
            $this->svg = SVG::fromFile( $source->value );

            if ( ! $this->svg ) {
                $this->status->step( 'Set Source' )->end(
                    'error',
                    'The provided SVG file is not valid.'
                );
                return $this;
            }
        }

        if ( ! $this->svg ) {
            $this->image = ImageManager::gd()->read( $source->value, FilePathImageDecoder::class );
        }
        else {
            $this->image = ImageManager::gd()->read( $this->svgResource(), NativeObjectDecoder::class );
        }

        $this->status->step( 'Set Source' )->end(
            'success',
            'Generated GD image from provided ' . $source->mimeType . ' source.'
        );


        return $this;
    }

    /**
     * @param int      $width
     * @param null|int $height
     *
     * @return GdImage | resource
     */
    private function svgResource( int $width = 512, ?int $height = null ) : GdImage | string {
        return $this->svg->toRasterImage( $width, $height ?? $width );
    }


    /**
     * Delete all generated files from the public root path.
     *
     * - This method does not check if the files were generated by this class.
     *
     * @return array
     */
    public function purgeExisting() : array {

        $purge = array_merge(
            [
                'favicon.ico',
                'favicon.svg',
                'safari-pinned-tab.svg',
                'manifest.json',
                'site.webmanifest', // We don't generate this file, but remove preexisting one prevent duplicates.
                'browserconfig.xml',
            ],
            array_keys( FaviconManager::SIZES ),
        );

        $purged = [];

        foreach ( $purge as $file ) {

            $path = $this->publicRoot->toString( $file );

            if ( file_exists( $path ) && File::remove( $path ) ) {
                clearstatcache( true, $path );
                $purged[] = $file;
            }
        }

        return $purged;
    }


}