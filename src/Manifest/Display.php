<?php

namespace Northrook\Favicon\Manifest;

enum Display : string
{
    case FullScreen = 'fullscreen';
    case Standalone = 'standalone';
    case MinimalUI = 'minimal-ui';
    case OverlayUI = 'window-controls-overlay';
    case Browser = 'browser';
}