"""
Ghost Kitchen Order Board SDK - Setup
"""

from setuptools import setup, find_packages

with open("README.md", "r", encoding="utf-8") as fh:
    long_description = fh.read()

setup(
    name="orderboard-sdk",
    version="1.0.0",
    author="Ghost Kitchen",
    description="Python SDK for the Ghost Kitchen Order Board API",
    long_description=long_description,
    long_description_content_type="text/markdown",
    url="https://github.com/ghost-kitchen/orderboard",
    packages=find_packages(),
    classifiers=[
        "Development Status :: 4 - Beta",
        "Intended Audience :: Developers",
        "License :: OSI Approved :: GNU Affero General Public License v3",
        "Operating System :: OS Independent",
        "Programming Language :: Python :: 3",
        "Programming Language :: Python :: 3.8",
        "Programming Language :: Python :: 3.9",
        "Programming Language :: Python :: 3.10",
        "Programming Language :: Python :: 3.11",
        "Programming Language :: Python :: 3.12",
    ],
    python_requires=">=3.8",
    install_requires=[],  # Zero external dependencies
)
