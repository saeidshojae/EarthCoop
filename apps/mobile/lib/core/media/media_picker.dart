import 'dart:typed_data';

class SelectedMedia {
  SelectedMedia({
    required this.bytes,
    required this.fileName,
    required this.mimeType,
  }) {
    if (bytes.isEmpty) {
      throw ArgumentError.value(bytes, 'bytes', 'must not be empty');
    }
    if (fileName.trim().isEmpty) {
      throw ArgumentError.value(fileName, 'fileName', 'must not be empty');
    }
    if (mimeType.trim().isEmpty) {
      throw ArgumentError.value(mimeType, 'mimeType', 'must not be empty');
    }
  }

  final Uint8List bytes;
  final String fileName;
  final String mimeType;
}

abstract interface class MediaPicker {
  Future<SelectedMedia?> pick();
}
