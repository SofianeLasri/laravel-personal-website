declare module 'photoswipe-dynamic-caption-plugin' {
    import PhotoSwipeLightbox from 'photoswipe/lightbox';

    interface DynamicCaptionOptions {
        type?: 'auto' | 'below' | 'above';
        captionContent?: string | ((slide: any) => string);
        horizontalPadding?: number;
        mobileCaptionOverlapRatio?: number;
        mobileLayoutBreakpoint?: number;
    }

    class PhotoSwipeDynamicCaption {
        constructor(lightbox: PhotoSwipeLightbox, options?: DynamicCaptionOptions);
    }

    export = PhotoSwipeDynamicCaption;
}