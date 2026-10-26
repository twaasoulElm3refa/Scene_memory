"""Move an MP4's index (moov) in front of the media data, like ffmpeg -movflags +faststart.

Browsers can only start playing a progressive MP4 once they have read moov. When moov
sits after mdat they must fetch the end of the file first (or all of it). This
rewrites the top-level boxes as ftyp, moov, everything else, and shifts every chunk
offset (stco/co64) by the size of the moved moov. The input is never modified.

    python scripts/video/faststart.py input.mp4 output.mp4
    python scripts/video/faststart.py --check file.mp4
"""
import struct
import sys

CONTAINERS = {b'moov', b'trak', b'mdia', b'minf', b'stbl', b'edts', b'dinf', b'udta', b'mvex'}


def top_level_boxes(data):
    boxes, offset = [], 0
    while offset < len(data):
        size, kind = struct.unpack('>I4s', data[offset:offset + 8])
        header = 8
        if size == 1:
            size = struct.unpack('>Q', data[offset + 8:offset + 16])[0]
            header = 16
        elif size == 0:
            size = len(data) - offset
        if size < header or offset + size > len(data):
            raise ValueError('Malformed box %r at %d' % (kind, offset))
        boxes.append((kind, offset, size))
        offset += size
    return boxes


def shift_chunk_offsets(moov, delta):
    moov = bytearray(moov)

    def walk(start, end):
        offset = start
        while offset + 8 <= end:
            size, kind = struct.unpack('>I4s', moov[offset:offset + 8])
            header = 8
            if size == 1:
                size = struct.unpack('>Q', moov[offset + 8:offset + 16])[0]
                header = 16
            if size < header or offset + size > end:
                raise ValueError('Malformed box %r inside moov' % kind)
            if kind in CONTAINERS:
                walk(offset + header, offset + size)
            elif kind in (b'stco', b'co64'):
                count = struct.unpack('>I', moov[offset + header + 4:offset + header + 8])[0]
                entry = 4 if kind == b'stco' else 8
                fmt = '>I' if kind == b'stco' else '>Q'
                for index in range(count):
                    at = offset + header + 8 + index * entry
                    value = struct.unpack(fmt, moov[at:at + entry])[0] + delta
                    if kind == b'stco' and value > 0xFFFFFFFF:
                        raise ValueError('Chunk offset overflow; needs co64')
                    moov[at:at + entry] = struct.pack(fmt, value)
            offset += size

    walk(8, len(moov))
    return bytes(moov)


def order(path):
    with open(path, 'rb') as handle:
        return [kind.decode('latin-1') for kind, _, _ in top_level_boxes(handle.read())]


def faststart(source, destination):
    with open(source, 'rb') as handle:
        data = handle.read()
    boxes = top_level_boxes(data)
    kinds = [kind for kind, _, _ in boxes]
    if b'moov' not in kinds or b'mdat' not in kinds:
        raise ValueError('Not a progressive MP4 (needs moov and mdat)')
    if kinds.index(b'moov') < kinds.index(b'mdat'):
        with open(destination, 'wb') as handle:
            handle.write(data)
        return 'already faststart'
    moov = next((data[o:o + s], s) for k, o, s in boxes if k == b'moov')
    head = [(k, o, s) for k, o, s in boxes if k == b'ftyp']
    rest = [(k, o, s) for k, o, s in boxes if k not in (b'ftyp', b'moov')]
    # Every chunk moves forward by the moov size (it now precedes them).
    patched = shift_chunk_offsets(moov[0], moov[1])
    with open(destination, 'wb') as handle:
        for _, offset, size in head:
            handle.write(data[offset:offset + size])
        handle.write(patched)
        for _, offset, size in rest:
            handle.write(data[offset:offset + size])
    return 'moved moov (%d bytes) before mdat' % moov[1]


if __name__ == '__main__':
    if len(sys.argv) == 3 and sys.argv[1] == '--check':
        print(' '.join(order(sys.argv[2])))
    elif len(sys.argv) == 3:
        print(faststart(sys.argv[1], sys.argv[2]))
        print(' '.join(order(sys.argv[2])))
    else:
        print(__doc__)
        sys.exit(2)
