<script setup lang="ts">
import ActiveButton from '@/components/public/Ui/Button/ActiveButton.vue';
import WhiteButton from '@/components/public/Ui/Button/WhiteButton.vue';
import LaravelLogo from '@/components/shapes/LaravelLogo.vue';
</script>

<template>
    <div class="border-border inline-flex w-full flex-col items-start justify-start self-stretch rounded-2xl border bg-gray-100">
        <div class="inline-flex items-center justify-start gap-2.5 self-stretch px-4 py-3">
            <div class="text-design-system-title justify-center">Framework</div>
        </div>
        <div class="border-border flex flex-1 flex-col items-start justify-start gap-2 self-stretch overflow-hidden rounded-2xl border bg-white p-2">
            <ActiveButton class="w-full rounded-lg !px-3 py-2">
                <div class="flex grow items-center gap-2">
                    <div class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-white p-2">
                        <LaravelLogo />
                    </div>
                    <div>Active</div>
                </div>
                <div class="text-design-system-paragraph shrink-0">4</div>
            </ActiveButton>

            <WhiteButton class="w-full rounded-lg border-none !px-3 py-2">
                <div class="flex grow items-center gap-2">
                    <div class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-white p-2">
                        <LaravelLogo />
                    </div>
                    <div>Active</div>
                </div>
                <div class="text-design-system-paragraph shrink-0">4</div>
            </WhiteButton>

            <WhiteButton class="w-full rounded-lg border-none !px-3 py-2">
                <div class="flex grow items-center gap-2">
                    <div class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-white p-2">
                        <LaravelLogo />
                    </div>
                    <div>Active</div>
                </div>
                <div class="text-design-system-paragraph shrink-0">4</div>
            </WhiteButton>

            <WhiteButton class="w-full rounded-lg border-none !px-3 py-2">
                <div class="flex grow items-center gap-2">
                    <div class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-white p-2">
                        <LaravelLogo />
                    </div>
                    <div>Active</div>
                </div>
                <div class="text-design-system-paragraph shrink-0">4</div>
            </WhiteButton>

            <WhiteButton class="w-full rounded-lg border-none !px-3 py-2">
                <div class="flex grow items-center gap-2">
                    <div class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-white p-2">
                        <LaravelLogo />
                    </div>
                    <div>Active</div>
                </div>
                <div class="text-design-system-paragraph shrink-0">4</div>
            </WhiteButton>
        </div>
    </div>
</template>

<style scoped></style>
