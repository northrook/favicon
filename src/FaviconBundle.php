<?php

namespace Northrook\Favicon;

use GdImage;
use Intervention\Image\Drivers\Gd\Decoders\GdImageDecoder;
use Intervention\Image\Drivers\Gd\Decoders\ImageObjectDecoder;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use Northrook\Logger\Log;
use Northrook\Support\File;
use Northrook\Types\Color\Hex;
use Northrook\Types\Path;
use stdClass;
use SVG\SVG;


class FaviconBundle extends stdClass
{

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


    private array                    $notices = [];
    public ?Hex                      $themeColor;
    public ?SVG                      $svg;
    public readonly IcoFileGenerator $ico;
    public readonly Manifest         $manifest;

    private Image   $image;
    private GdImage $masterCache;


    /**
     * @param Path|string  $source  Ideally an SVG or PNG file. If no SVG is provided here, please add one via {@see FaviconBundle::add()}
     * @param string       $cacheDir
     */
    public function __construct(
        private Path | string   $source,
        Manifest                $manifest,
        Hex | string            $color,
        private readonly string $cacheDir = __DIR__ . '/cache',
    ) {

        $this->manifest = $manifest;

        $this->themeColor = is_string( $color ) ? new Hex( $color ) : $color;

        if ( is_string( $this->source ) ) {
            $this->source = new Path( $this->source );
        }

        if ( !$this->source->exists ) {
            Log::Error( '{source} is not a valid file.', [ 'source' => $this->source, ], );
            return;
        };

        if ( !in_array( $this->source->extension, [ 'svg', 'png' ] ) ) {
            $this->notices[] = 'The provided source image should be a SVG or PNG file.';
        };

        if ( !str_contains( mime_content_type( $this->source->value ), 'image/' ) ) {
            $this->notices = [ 'The provided source image must be an image.' ];
            Log::Error(
                '{source} is not an image.', [ 'source' => $this->source, ],
            );
            return;
        };

        if ( 'svg' == $this->source->extension ) {
            $this->notices[]   = 'The provided source is a vector file. A PNG version has been generated.';
            $this->svg         = SVG::fromFile( $this->source->value );
            $this->masterCache = $this->svgToPng();
        }

        $this->ico   = new IcoFileGenerator( $this->masterCache );
        $this->image = ImageManager::gd()->read( $this->masterCache, GdImageDecoder::class );
    }



    public function purge( Path | string $publicRootPath ) : array {
        if ( is_string( $publicRootPath ) ) {
            $publicRootPath = new Path( $publicRootPath );
        }
        $purge = array_merge(
            [
                'favicon.ico', 'favicon.svg', 'safari-pinned-tab.svg', 'manifest.json', 'site.webmanifest',
                'browserconfig.xml',
            ],
            array_keys( self::SIZES ),
        );

        $purged = [];

        foreach ( $purge as $file ) {
            if ( file_exists( $publicRootPath . $file ) && File::remove( $publicRootPath . $file ) ) {
                $purged[] = $file;
            };
        }

        return $purged;
    }

    public function save( Path | string $publicRootPath ) : array {
        if ( is_string( $publicRootPath ) ) {
            $publicRootPath = new Path( $publicRootPath );
        }

        if ( !$publicRootPath->isDir ) {
            $this->notices[] = 'The provided public root path is not a valid directory.';
            Log::Error(
                '{publicRootPath} is not a directory.', [ 'publicRootPath' => $publicRootPath, ],
            );

            return $this->notices;
        }

        // dd( $publicRootPath );
        $this->notices[ 'favicon.ico' ] = $this->ico->save( $publicRootPath . 'favicon.ico' );
        $this->notices[ 'favicon.svg' ] = File::save( $publicRootPath . 'favicon.svg', $this->svg->toXMLString() );


        // TODO : Parse the SVG and replace each color with the theme color
        // $safari = $this->svg->toXMLString();
        // $this->notices[ 'safari-pinned-tab.svg' ] = File::save( $publicRootPath . 'safari-pinned-tab.svg',  );

        // $this->image->save( $publicRootPath . 'favicon.png', 100 );

        foreach ( self::SIZES as $name => $size ) {
            $image  = clone $this->image;
            $width  = is_array( $size ) ? $size[ 0 ] : $size;
            $height = is_array( $size ) ? $size[ 1 ] : $size;
            $image->scaleDown( $width, $height );
            $image->save( $publicRootPath . $name );
        }

        $this->manifest->icons( $this->links() );

        $this->notices[ 'manifest.json' ] =
            File::save( $publicRootPath . 'manifest.json', $this->manifest->generate() );

        return  $this->notices;
    }

    public function links() : array {
        return [
            [
                'rel'  => 'apple-touch-icon',
                'type' => 'image/png',
                'href' => '/apple-touch-icon.png',
            ],
            [
                'rel'  => 'icon',
                'type' => 'image/png',
                'href' => '/favicon-32x32.png',
            ],
            [
                'rel'  => 'icon',
                'type' => 'image/png',
                'href' => '/android-chrome-192x192.png',
            ],
            [
                'rel'  => 'icon',
                'type' => 'image/png',
                'href' => '/favicon-16x16.png',
            ],
            [
                'rel'  => 'icon',
                'type' => 'image/svg+xml',
                'href' => '/favicon.svg',
            ],
            [
                'rel'  => 'manifest',
                'href' => '/manifest.json',
            ],
            [
                'rel'   => 'mask-icon',
                'href'  => '/safari-pinned-tab.svg',
                'color' => $this->themeColor,
            ],
            [
                'rel'  => 'shortcut icon',
                'href' => '/favicon.ico',
            ],
        ];
    }


    /**
     * @param int       $width
     * @param null|int  $height
     *
     * @return GdImage | resource
     */
    public function svgToPng( int $width = 512, ?int $height = null ) : GdImage | string {
        return $this->svg->toRasterImage( $width, $height ?? $width );
    }
}