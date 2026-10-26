#!/usr/bin/env bash
# Linux/macOS equivalent of scripts/video/transcode-windows.ps1 + faststart.py.
# Produces the same deliverables from the untouched original:
#   public/video/Final_Scemory-web.mp4     H.264 1080p30, no audio (always muted), faststart
#   public/video/Final_Scemory-poster.jpg  first frame, shown until playback starts
# The committed files were made on Windows with the built-in Media Foundation encoder
# at 6 Mbps (aligned-frame PSNR 43.6-49.7 dB against the original); CRF 20 below is
# the usual x264 equivalent. Requires an ffmpeg you trust.
set -euo pipefail
cd "$(dirname "$0")/../.."
src=public/video/Final_Scemory.mp4
ffmpeg -nostdin -y -i "$src" -an -c:v libx264 -preset slow -crf 20 -profile:v high \
    -pix_fmt yuv420p -r 30 -movflags +faststart public/video/Final_Scemory-web.mp4
ffmpeg -nostdin -y -i public/video/Final_Scemory-web.mp4 -frames:v 1 -q:v 3 public/video/Final_Scemory-poster.jpg
ls -la public/video
