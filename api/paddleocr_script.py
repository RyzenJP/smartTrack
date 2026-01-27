#!/usr/bin/env python3
"""
PaddleOCR Python Script for PHP Integration
This script extracts text from images using PaddleOCR
"""

import sys
import os
import json

def extract_text_with_paddleocr(image_path):
    """Extract text from image using PaddleOCR"""
    try:
        # Try to import PaddleOCR
        from paddleocr import PaddleOCR
        
        # Initialize PaddleOCR
        # Use 'en' for English, 'ch' for Chinese, or 'ch_en' for both
        ocr = PaddleOCR(use_angle_cls=True, lang='en')
        
        # Perform OCR
        result = ocr.ocr(image_path, cls=True)
        
        # Extract text from results
        extracted_text = ""
        if result and result[0]:
            for line in result[0]:
                if line and len(line) >= 2:
                    text = line[1][0]  # Extract text from the result
                    confidence = line[1][1]  # Extract confidence score
                    if confidence > 0.5:  # Only include text with good confidence
                        extracted_text += text + "\n"
        
        return extracted_text.strip()
        
    except ImportError:
        return "ERROR: PaddleOCR not installed. Install with: pip install paddlepaddle paddleocr"
    except Exception as e:
        return f"ERROR: {str(e)}"

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
        print("Usage: python paddleocr_script.py <image_path>")
        sys.exit(1)
    
    image_path = sys.argv[1]
    
    if not os.path.exists(image_path):
        print(f"ERROR: Image file not found: {image_path}")
        sys.exit(1)
    
    # Try PaddleOCR first, then EasyOCR as fallback
    text = extract_text_with_paddleocr(image_path)
    
    if text.startswith("ERROR"):
        # Fallback to EasyOCR
        text = extract_text_with_easyocr(image_path)
    
    if text.startswith("ERROR"):
        # Both failed
        print(text)
        sys.exit(1)
    else:
        print(text)

if __name__ == "__main__":
    main()

