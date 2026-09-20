#!/usr/bin/env python3
"""Entry point:  python run.py"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from app.main import main

if __name__ == "__main__":
    sys.exit(main())
