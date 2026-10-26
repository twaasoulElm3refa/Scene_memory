param(
    [Parameter(Mandatory = $true)][string]$Source,
    [Parameter(Mandatory = $true)][string]$Destination,
    [int]$Width = 1920,
    [int]$Height = 1080,
    [int]$FrameRate = 30,
    [int]$VideoBitrate = 6000000
)
# Re-encodes the homepage video with Windows' built-in Media Foundation encoder
# (WinRT MediaTranscoder): H.264 MP4, same resolution and frame rate, no audio track
# (the video is always muted). Run scripts/video/faststart.py on the output so the
# index (moov) comes first and playback starts before the whole file downloads.
# On Linux/macOS use scripts/video/encode-ffmpeg.sh instead.
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Runtime.WindowsRuntime
$methods = [System.WindowsRuntimeSystemExtensions].GetMethods()
$asTaskOperation = ($methods | Where-Object { $_.Name -eq 'AsTask' -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1' })[0]
$asTaskProgress = ($methods | Where-Object { $_.Name -eq 'AsTask' -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncActionWithProgress`1' })[0]
function Await($operation, [Type]$resultType) {
    $task = $asTaskOperation.MakeGenericMethod($resultType).Invoke($null, @($operation))
    $task.Wait(-1) | Out-Null
    $task.Result
}

[Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime] | Out-Null
[Windows.Storage.StorageFolder, Windows.Storage, ContentType = WindowsRuntime] | Out-Null
[Windows.Media.Transcoding.MediaTranscoder, Windows.Media.Transcoding, ContentType = WindowsRuntime] | Out-Null
[Windows.Media.Transcoding.PrepareTranscodeResult, Windows.Media.Transcoding, ContentType = WindowsRuntime] | Out-Null
[Windows.Media.MediaProperties.MediaEncodingProfile, Windows.Media.MediaProperties, ContentType = WindowsRuntime] | Out-Null

$sourcePath = (Resolve-Path $Source).Path
$destinationFolder = Split-Path -Parent ([System.IO.Path]::GetFullPath($Destination))
$destinationName = Split-Path -Leaf $Destination
$input = Await ([Windows.Storage.StorageFile]::GetFileFromPathAsync($sourcePath)) ([Windows.Storage.StorageFile])
$folder = Await ([Windows.Storage.StorageFolder]::GetFolderFromPathAsync($destinationFolder)) ([Windows.Storage.StorageFolder])
$output = Await ($folder.CreateFileAsync($destinationName, [Windows.Storage.CreationCollisionOption]::ReplaceExisting)) ([Windows.Storage.StorageFile])

$profile = [Windows.Media.MediaProperties.MediaEncodingProfile]::CreateMp4([Windows.Media.MediaProperties.VideoEncodingQuality]::HD1080p)
$profile.Video.Width = $Width
$profile.Video.Height = $Height
$profile.Video.Bitrate = $VideoBitrate
$profile.Video.FrameRate.Numerator = $FrameRate
$profile.Video.FrameRate.Denominator = 1
$profile.Video.ProfileId = [Windows.Media.MediaProperties.H264ProfileIds]::High
$profile.Audio = $null

$transcoder = New-Object Windows.Media.Transcoding.MediaTranscoder
$transcoder.HardwareAccelerationEnabled = $false   # software encoder: deterministic quality
$prepared = Await ($transcoder.PrepareFileTranscodeAsync($input, $output, $profile)) ([Windows.Media.Transcoding.PrepareTranscodeResult])
if (-not $prepared.CanTranscode) { throw "Cannot transcode: $($prepared.FailureReason)" }
$started = Get-Date
$task = $asTaskProgress.MakeGenericMethod([double]).Invoke($null, @($prepared.TranscodeAsync()))
$task.Wait(-1) | Out-Null
if ($task.IsFaulted) { throw $task.Exception }
"Encoded {0} -> {1} in {2:N0} s ({3:N2} MB)" -f $sourcePath, $Destination, ((Get-Date) - $started).TotalSeconds, ((Get-Item $Destination).Length / 1MB)
