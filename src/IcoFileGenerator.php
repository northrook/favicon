<?php

declare( strict_types = 1 );

namespace Northrook\Favicon;

use GdImage;
use Northrook\Logger\Log;
use Symfony\Component\Filesystem\Filesystem;

final class IcoFileGenerator
{
    private array $images;

    // Ensure prerequisites are met.
    private readonly bool $preflight;


    /**
     * @param null|GdImage|string  $source  Optional. Path to the source image file.
     * @param ?array               $sizes   Optional. An array of sizes (each size is an array with a width and height) that the source image should be rendered at in the generated ICO file. If sizes are not supplied, the size of the source image will be used.
     */
    public function __construct(
        GdImage | string | null $source = null,
        ?array                  $sizes = [
            [ 16, 16 ],
            [ 24, 24 ],
            [ 32, 32 ],
            // [ 48, 48 ],
            // [ 64, 64 ],
        ],
    ) {

        $this->preflight = class_exists( GdImage::class );

        if ( !$this->preflight ) {
            Log::Error(
                "{class} is required to generate {ico} files.
                Please install the GD PHP extension.
                No file was generated.",
                [
                    'class' => 'GdImage',
                    'ico'   => '.ico',
                    'docs'  => 'https://www.php.net/manual/en/image.installation.php',
                ],
            );
        }

        if ( $source ) {
            $this->add( $source, $sizes ?? [] );
        }
    }

    /**
     * Add an image to the generator.
     *
     * This function adds a source image to the generator. It serves two main purposes: add a source image if one was
     * not supplied to the constructor and to add additional source images so that different images can be supplied for
     * different sized images in the resulting ICO file. For instance, a small source image can be used for the small
     * resolutions while a larger source image can be used for large resolutions.
     *
     * @param GdImage|string  $image  Path to the source image file.
     * @param array           $sizes  Optional. An array of sizes (each size is an array with a width and height) that the source image should be rendered at in the generated ICO file. If sizes are not supplied, the size of the source image will be used.
     *
     * @return boolean true on success and false on failure.
     */
    public function add( GdImage | string $image, array $sizes = [] ) : bool {

        if ( !$this->preflight ) {
            return false;
        }

        $image = ( $image instanceof GdImage ) ? $image : $this->loadSourceImage( $image );

        if ( !$image ) {
            return false;
        }

        if ( empty( $sizes ) ) {
            $sizes = [ imagesx( $image ), imagesy( $image ) ];
        }

        // If just a single size was passed, put it in array.
        if ( !is_array( $sizes[ 0 ] ) ) {
            $sizes = [ $sizes ];
        }

        foreach ( $sizes as $size ) {
            [ $width, $height ] = $size;

            $new = imagecreatetruecolor( $width, $height );

            imagecolortransparent( $new, imagecolorallocatealpha( $new, 0, 0, 0, 127 ) );
            imagealphablending( $new, false );
            imagesavealpha( $new, true );

            $source_width  = imagesx( $image );
            $source_height = imagesy( $image );

            if ( false === imagecopyresampled(
                    $new, $image, 0, 0, 0, 0, $width, $height, $source_width, $source_height,
                ) ) {
                continue;
            }

            $this->addImageData( $new );
        }

        return true;
    }

    /**
     * Write the ICO file data to a file path.
     *
     * @param string  $path  Path to save the ICO file data into.
     *
     * @return boolean true on success and false on failure.
     */
    public function save( string $path ) : bool {

        ( new Filesystem() )->mkdir( dirname( $path ) );

        if ( !$this->preflight ) {
            return false;
        }

        if ( false === ( $data = $this->getIcoData() ) ) {
            return false;
        }

        if ( false === ( $fh = fopen( $path, 'w' ) ) ) {
            return false;
        }

        if ( false === ( fwrite( $fh, $data ) ) ) {
            fclose( $fh );
            return false;
        }

        fclose( $fh );

        return true;
    }

    /**
     * Generate the final ICO data by creating a file header and adding the image data.
     */
    private function getIcoData() : bool | string {

        if ( empty( $this->images ) ) {
            return false;
        }

        $data       = pack( 'vvv', 0, 1, count( $this->images ) );
        $pixel_data = '';

        $icon_dir_entry_size = 16;

        $offset = 6 + ( $icon_dir_entry_size * count( $this->images ) );

        foreach ( $this->images as $image ) {
            $data       .= pack(
                'CCCCvvVV', $image[ 'width' ], $image[ 'height' ], $image[ 'color_palette_colors' ], 0, 1,
                $image[ 'bits_per_pixel' ], $image[ 'size' ], $offset,
            );
            $pixel_data .= $image[ 'data' ];

            $offset += $image[ 'size' ];
        }

        $data .= $pixel_data;

        return $data;
    }

    /**
     * Display the ICO file data.
     *
     */
    public function render() : void {
        if ( !$this->preflight ) {
            return;
        }

        header( 'Content-Type: image/x-icon' );
        print $this->getIcoData();
    }


    /**
     * Take a GD image resource and change it into a raw BMP format.
     *
     */
    private function addImageData( GdImage $image ) : void {

        $width  = imagesx( $image );
        $height = imagesy( $image );

        $pixel_data = [];

        $opacity_data        = [];
        $current_opacity_val = 0;

        for ( $y = $height - 1; $y >= 0; $y-- ) {
            for ( $x = 0; $x < $width; $x++ ) {
                $color = imagecolorat( $image, $x, $y );

                $alpha = ( $color & 0x7F000000 ) >> 24;
                //$alpha = ( 1 - ( $alpha / 127 ) ) * 255;
                $alpha = round( ( 1 - ( $alpha / 127 ) ) * 255 );

                $color &= 0xFFFFFF;
                $color |= 0xFF000000 & ( $alpha << 24 );

                $pixel_data[] = $color;


                $opacity = ( $alpha <= 127 ) ? 1 : 0;

                $current_opacity_val = ( $current_opacity_val << 1 ) | $opacity;

                if ( ( ( $x + 1 ) % 32 ) == 0 ) {
                    $opacity_data[]      = $current_opacity_val;
                    $current_opacity_val = 0;
                }
            }

            if ( ( $x % 32 ) > 0 ) {
                while ( ( $x++ % 32 ) > 0 ) {
                    $current_opacity_val = $current_opacity_val << 1;
                }

                $opacity_data[]      = $current_opacity_val;
                $current_opacity_val = 0;
            }
        }

        $image_header_size = 40;
        $color_mask_size   = $width * $height * 4;
        $opacity_mask_size = ( ceil( $width / 32 ) * 4 ) * $height;


        $data = pack( 'VVVvvVVVVVV', 40, $width, ( $height * 2 ), 1, 32, 0, 0, 0, 0, 0, 0 );

        foreach ( $pixel_data as $color ) {
            $data .= pack( 'V', $color );
        }

        foreach ( $opacity_data as $opacity ) {
            $data .= pack( 'N', $opacity );
        }


        $layer = [
            'width'                => $width,
            'height'               => $height,
            'color_palette_colors' => 0,
            'bits_per_pixel'       => 32,
            'size'                 => $image_header_size + $color_mask_size + $opacity_mask_size,
            'data'                 => $data,
        ];

        $this->images[] = $layer;
    }


    /**
     * Read in the source image file and convert it into a {@see GdImage} resource.
     *
     * @param string  $path  Path to the source image file.
     *
     * @return GdImage|false Resource on success and false on failure.
     */
    private function loadSourceImage( string $path ) : GdImage | false {

        // Ensure that the file exists and that the file is an image.
        if ( false === getimagesize( $path ) ||
             false === ( $contents = file_get_contents( $path ) )
        ) {
            return false;
        }

        return imagecreatefromstring( $contents );
    }
}