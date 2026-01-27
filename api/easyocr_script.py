#!/usr/bin/env python3
"""
EasyOCR Python Script for PHP Integration
This script extracts text from images using EasyOCR
"""

import sys
import os

def extract_text_with_easyocr(image_path):
    """Extract text from image using EasyOCR"""
    try:
        import easyocr
        
        # Initialize EasyOCR
        reader = easyocr.Reader(['en'])
        
        # Perform OCR
        result = reader.readtext(image_path)
        
        # Extract text from results
        extracted_text = ""
        for (bbox, text, confidence) in result:
            if confidence > 0.5:  # Only include text with good confidence
                extracted_text += text + "\n"
        
        return extracted_text.strip()
        
    except ImportError:
        return "ERROR: EasyOCR not installed. Install with: pip install easyocr"
    except Exception as e:
        return f"ERROR: {str(e)}"

def main():
    if len(sys.argv) != 2:
        print("Usage: python easyocr_script.py <image_path>")
        sys.exit(1)
    
    image_path = sys.argv[1]
    
    if not os.path.exists(image_path):
        print(f"ERROR: Image file not found: {image_path}")
        sys.exit(1)
    
    text = extract_text_with_easyocr(image_path)
    
    if text.startswith("ERROR"):
        print(text)
        sys.exit(1)
    else:
        print(text)

if __name__ == "__main__":
    main()

